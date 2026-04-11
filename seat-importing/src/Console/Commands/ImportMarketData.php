<?php

namespace Apokavkos\SeatImporting\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Apokavkos\SeatImporting\Models\MarketHub;
use Apokavkos\SeatImporting\Models\MarketImportLog;
use Apokavkos\SeatImporting\Services\MarketMetricsService;
use Seat\Eveapi\Models\Sde\InvType;

class ImportMarketData extends Command
{
    protected $signature = 'seat:importing:import
        {--hub=          : Hub ID to import for}
        {--jita-region=10000002 : Region ID for Jita Prices}
        {--download      : Force fresh CSV download}
        {--simulate      : Simulate markup if local market empty}';

    protected $description = 'Import market data from Fuzzwork CSV.';

    private MarketMetricsService $metricsService;

    public function __construct(MarketMetricsService $metricsService)
    {
        parent::__construct();
        $this->metricsService = $metricsService;
    }

    public function handle(): int
    {
        $hubs = $this->option('hub') ? MarketHub::where('id', $this->option('hub'))->where('is_active', true)->get() : MarketHub::where('is_active', true)->get();
        if ($hubs->isEmpty()) return self::SUCCESS;

        $jitaRegionId = (int)$this->option('jita-region');
        $csvPath = storage_path('app/seat-importing/aggregatecsv.csv.gz');
        if ($this->option('download') || !file_exists($csvPath)) {
            $this->info("Downloading global aggregates...");
            File::ensureDirectoryExists(dirname($csvPath));
            Http::sink($csvPath)->get("https://market.fuzzwork.co.uk/aggregatecsv.csv.gz");
        }

        $targetRegionIds = $hubs->pluck('region_id')->push($jitaRegionId)->unique()->filter()->toArray();
        foreach ($hubs as $hub) {
            if (!$hub->region_id && $hub->solar_system_id) {
                $rid = DB::table('solar_systems')->where('system_id', $hub->solar_system_id)->value('region_id');
                if ($rid) $targetRegionIds[] = $rid;
            }
            if (!$hub->region_id && $hub->structure_id) {
                $rid = DB::table('universe_structures as us')->join('solar_systems as ss', 'us.solar_system_id', '=', 'ss.system_id')->where('us.structure_id', $hub->structure_id)->value('ss.region_id');
                if ($rid) $targetRegionIds[] = $rid;
            }
        }
        $targetRegionIds = array_unique($targetRegionIds);

        $this->info("Parsing CSV for regions: " . implode(',', $targetRegionIds));
        $marketData = $this->parseGzippedCsv($csvPath, $targetRegionIds);
        
        $this->info('Loading metadata from SDE...');
        $sdeTypes = $this->loadSdeTypes(array_keys($marketData[$jitaRegionId] ?? []));

        foreach ($hubs as $hub) {
            $this->info("Processing Hub: {$hub->name}");
            $hubRegionId = $hub->region_id;
            if (!$hubRegionId) {
                if ($hub->solar_system_id) $hubRegionId = DB::table('solar_systems')->where('system_id', $hub->solar_system_id)->value('region_id');
                elseif ($hub->structure_id) {
                    $hubRegionId = DB::table('universe_structures as us')->join('solar_systems as ss', 'us.solar_system_id', '=', 'ss.system_id')->where('us.structure_id', $hub->structure_id)->value('ss.region_id');
                }
            }

            $log = MarketImportLog::create(['hub_id' => $hub->id, 'source' => 'fuzzwork_csv', 'status' => 'running', 'started_at' => Carbon::now()]);

            try {
                [$processed, $failed] = $this->importHubData($hub, $marketData[$jitaRegionId] ?? [], $marketData[$hubRegionId] ?? [], $sdeTypes, (bool)$this->option('simulate'));
                $log->update(['status' => 'complete', 'rows_processed' => $processed, 'rows_failed' => $failed, 'completed_at' => Carbon::now()]);
                $this->metricsService->flushHubCache($hub->id);
                $this->metricsService->warmHubCache($hub->id);
                $this->info("  ✓ {$processed} items updated.");
            } catch (\Exception $e) {
                $log->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'completed_at' => Carbon::now()]);
                $this->error("  ✗ Failed: " . $e->getMessage());
            }
        }
        return self::SUCCESS;
    }

    private function parseGzippedCsv(string $path, array $targetRegionIds): array
    {
        $regions = [];
        $handle = gzopen($path, 'rb');
        if ($handle === false) throw new \RuntimeException("Cannot open file: {$path}");
        $headers = null;
        while (($line = $this->fgetcsv_gz($handle)) !== false) {
            if ($headers === null) { $headers = array_map('strtolower', array_map('trim', $line)); continue; }
            $parts = explode('|', $line[0]);
            if (count($parts) < 3) continue;
            $regionId = (int)$parts[0];
            if (!in_array($regionId, $targetRegionIds)) continue;
            $typeId = (int)$parts[1];
            if (!isset($regions[$regionId][$typeId])) { $regions[$regionId][$typeId] = ['sell_min' => 0, 'buy_max' => 0, 'volume' => 0, 'orders' => 0]; }
            if ($parts[2] === 'false') { $regions[$regionId][$typeId]['sell_min'] = (float)($line[3] ?? 0); $regions[$regionId][$typeId]['volume'] = (float)($line[6] ?? 0); $regions[$regionId][$typeId]['orders'] = (int)($line[7] ?? 0); }
            else { $regions[$regionId][$typeId]['buy_max'] = (float)($line[2] ?? 0); }
        }
        gzclose($handle);
        return $regions;
    }

    private function fgetcsv_gz($handle) {
        $line = gzgets($handle, 16384);
        if ($line === false) return false;
        return str_getcsv($line, ',', '"', '\\');
    }

    private function importHubData(MarketHub $hub, array $jitaData, array $localData, array $sdeTypes, bool $simulate): array
    {
        $iskPerM3 = $hub->effectiveIskPerM3();
        $dataDate = Carbon::today()->toDateString();
        $processed = 0; $failed = 0;

        foreach (array_chunk(array_keys($jitaData), 500) as $chunk) {
            $upsertRows = [];
            foreach ($chunk as $typeId) {
                try {
                    $sde = $sdeTypes[$typeId] ?? null;
                    $jitaSell = (float)($jitaData[$typeId]['sell_min'] ?? 0);
                    $localSell = (float)($localData[$typeId]['sell_min'] ?? 0);
                    $localStock = (int)($localData[$typeId]['orders'] ?? 0);
                    if ($localSell <= 0) { $localSell = $simulate ? $jitaSell * 1.25 : $jitaSell; $localStock = 0; }
                    $metrics = $this->metricsService->calculateMetrics(['local_sell' => $localSell, 'jita_sell' => $jitaSell, 'weekly_volume' => (float)($jitaData[$typeId]['volume'] ?? 0) / 4], (float)($sde['volume'] ?? 0), $iskPerM3);
                    $upsertRows[] = [
                        'hub_id' => $hub->id, 'type_id' => $typeId, 'type_name' => $sde['name'] ?? "Type #{$typeId}", 'group_name' => $sde['group'] ?? null, 'category_name' => $sde['category'] ?? null,
                        'local_sell_price' => $localSell, 'local_buy_price' => (float)($localData[$typeId]['buy_max'] ?? 0), 'jita_sell_price' => $jitaSell, 'jita_buy_price' => (float)($jitaData[$typeId]['buy_max'] ?? 0),
                        'current_stock' => $localStock, 'weekly_volume' => (float)($jitaData[$typeId]['volume'] ?? 0) / 4, 'volume_m3' => (float)($sde['volume'] ?? 0), 'import_cost' => $metrics['import_cost'], 'markup_pct' => $metrics['markup_pct'], 'weekly_profit' => $metrics['weekly_profit'],
                        'data_date' => $dataDate, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
                    ];
                    $processed++;
                } catch (\Exception $e) { $failed++; }
            }
            DB::table('market_item_data')->upsert($upsertRows, ['hub_id', 'type_id', 'data_date'], ['type_name', 'group_name', 'category_name', 'local_sell_price', 'local_buy_price', 'jita_sell_price', 'jita_buy_price', 'current_stock', 'weekly_volume', 'volume_m3', 'import_cost', 'markup_pct', 'weekly_profit', 'updated_at']);
        }
        return [$processed, $failed];
    }

    private function loadSdeTypes(array $typeIds): array
    {
        $result = [];
        $data = DB::table('invTypes as t')
            ->join('invGroups as g', 't.groupID', '=', 'g.groupID')
            ->join('invCategories as c', 'g.categoryID', '=', 'c.categoryID')
            ->whereIn('t.typeID', array_unique($typeIds))
            ->select('t.typeID', 't.typeName', 't.volume', 'g.groupName', 'c.categoryName')
            ->get();

        foreach ($data as $row) {
            $result[(int)$row->typeID] = [
                'name' => $row->typeName,
                'volume' => (float)$row->volume,
                'group' => $row->groupName,
                'category' => $row->categoryName
            ];
        }
        return $result;
    }
}
