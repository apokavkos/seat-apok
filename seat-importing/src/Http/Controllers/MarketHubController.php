<?php

namespace Apokavkos\SeatImporting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Apokavkos\SeatImporting\Models\MarketHub;
use Apokavkos\SeatImporting\Models\MarketItemData;
use Apokavkos\SeatImporting\Models\MarketSetting;
use Apokavkos\SeatImporting\Models\MarketImportLog;
use Apokavkos\SeatImporting\Models\StockingList;
use Apokavkos\SeatImporting\Models\StockingItem;
use Apokavkos\SeatImporting\Services\MarketMetricsService;
use Apokavkos\SeatImporting\Jobs\ProcessMarketImport;
use Seat\Eveapi\Models\Sde\SolarSystem;
use Seat\Eveapi\Models\Sde\Region;
use Seat\Eveapi\Models\Universe\UniverseStructure;

class MarketHubController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly MarketMetricsService $metrics) {}

    public function index(Request $request): mixed
    {
        $hub = MarketHub::where('is_active', true)->orderBy('name')->first();
        if ($hub) return redirect()->route('seat-importing.hub.show', $hub);
        return view('seat-importing::dashboard', ['hubs' => collect(), 'selectedHub' => null, 'markupItems' => collect(), 'lowStockItems' => collect(), 'topMarkupItems' => collect(), 'topTotalItems' => collect(), 'categories' => [], 'groups' => []]);
    }

    public function show(MarketHub $hub): mixed
    {
        $hubs = Cache::remember(config('seat-importing.cache.prefix') . ':hub_list', config('seat-importing.cache.hub_list'), fn () => MarketHub::where('is_active', true)->orderBy('name')->get());
        $metricsData = $this->metrics->getHubMetrics($hub);
        $metadata = Cache::remember("seat-importing:metadata:{$hub->id}", 3600, fn() => [
            'categories' => MarketItemData::where('hub_id', $hub->id)->latestDateSubquery($hub->id)->whereNotNull('category_name')->distinct()->orderBy('category_name')->pluck('category_name')->toArray(),
            'groups' => MarketItemData::where('hub_id', $hub->id)->latestDateSubquery($hub->id)->whereNotNull('group_name')->distinct()->orderBy('group_name')->pluck('group_name')->toArray(),
        ]);

        return view('seat-importing::dashboard', [
            'hubs' => $hubs, 'selectedHub' => $hub, 'markupItems' => $metricsData['markup'], 'lowStockItems' => $metricsData['low_stock'], 'topMarkupItems' => $metricsData['top_markup'], 'topTotalItems' => $metricsData['top_total'], 'lastImport' => $metricsData['last_import'], 'categories' => $metadata['categories'], 'groups' => $metadata['groups'],
        ]);
    }

    public function stockingDashboard()
    {
        $hubs = MarketHub::where('is_active', true)->orderBy('name')->get();
        $lists = StockingList::where('user_id', auth()->id())->with(['items.type', 'hub'])->orderBy('created_at', 'desc')->get();
        foreach ($lists as $list) {
            foreach ($list->items as $item) {
                // Fetch live data specifically for the linked hub
                $item->live_data = MarketItemData::where('hub_id', $list->hub_id)
                    ->where('type_id', $item->type_id)
                    ->latest('data_date')
                    ->first();
            }
        }
        return view('seat-importing::stocking', compact('lists', 'hubs'));
    }

    public function saveStockingList(Request $request): JsonResponse
    {
        $request->validate(['label' => 'required|string|max:100', 'items' => 'required|array', 'hub_id' => 'nullable|integer']);
        $list = StockingList::create(['user_id' => auth()->id(), 'hub_id' => $request->hub_id, 'label' => $request->label]);
        foreach ($request->items as $item) {
            StockingItem::create(['list_id' => $list->id, 'type_id' => $item['type_id'], 'quantity' => $item['qty']]);
        }
        return response()->json(['success' => 'List saved to Stocking Dashboard.']);
    }

    public function destroyStockingList(StockingList $list): RedirectResponse
    {
        if ($list->user_id !== auth()->id()) abort(403);
        $list->delete();
        return redirect()->back()->with('success', 'Stocking list removed.');
    }

    public function destroyStockingItem(StockingItem $item): JsonResponse
    {
        if ($item->list->user_id !== auth()->id()) abort(403);
        $item->delete();
        return response()->json(['success' => true]);
    }

    public function storeHub(Request $request): RedirectResponse
    {
        $this->authorize('market.settings');
        $validated = $request->validate(['name' => 'required|string|max:100', 'solar_system_id' => 'nullable|integer', 'structure_id' => 'nullable|integer', 'region_id' => 'nullable|integer', 'isk_per_m3' => 'required|numeric|min:0', 'is_active' => 'boolean', 'notes' => 'nullable|string|max:2000']);
        $validated['is_active'] = $request->boolean('is_active', true);
        MarketHub::create($validated);
        Cache::forget(config('seat-importing.cache.prefix') . ':hub_list');
        return redirect()->route('seat-importing.settings')->with('success', 'Hub created successfully.');
    }

    public function updateHub(Request $request, MarketHub $hub): RedirectResponse
    {
        $this->authorize('market.settings');
        $validated = $request->validate(['name' => 'required|string|max:100', 'solar_system_id' => 'nullable|integer', 'structure_id' => 'nullable|integer', 'region_id' => 'nullable|integer', 'isk_per_m3' => 'required|numeric|min:0', 'is_active' => 'boolean', 'notes' => 'nullable|string|max:2000']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $hub->update($validated);
        Cache::forget(config('seat-importing.cache.prefix') . ':hub_list');
        $this->metrics->flushHubCache($hub->id);
        return redirect()->route('seat-importing.settings')->with('success', 'Hub updated successfully.');
    }

    public function destroyHub(MarketHub $hub): RedirectResponse
    {
        $this->authorize('market.settings');
        $this->metrics->flushHubCache($hub->id);
        $hub->delete();
        Cache::forget(config('seat-importing.cache.prefix') . ':hub_list');
        return redirect()->route('seat-importing.settings')->with('success', 'Hub deleted.');
    }

    public function settings(): mixed
    {
        $hubs = MarketHub::orderBy('name')->get();
        $globalSettings = ['isk_per_m3' => MarketSetting::get('isk_per_m3', null, config('seat-importing.default_isk_per_m3')), 'markup_threshold_pct' => MarketSetting::get('markup_threshold_pct', null, config('seat-importing.markup_threshold_pct')), 'stock_low_threshold' => MarketSetting::get('stock_low_threshold_pct', null, config('seat-importing.stock_low_threshold_pct')), 'discord_webhook_url' => MarketSetting::get('discord_webhook_url')];
        $recentLogs = MarketImportLog::latest()->limit(10)->get();
        return view('seat-importing::settings', compact('hubs', 'globalSettings', 'recentLogs'));
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $this->authorize('market.settings');
        $validated = $request->validate(['isk_per_m3' => 'required|numeric|min:0', 'markup_threshold_pct' => 'required|numeric|min:0|max:10000', 'stock_low_threshold' => 'required|numeric|min:0|max:10000', 'discord_webhook_url' => 'nullable|url']);
        MarketSetting::setValue('isk_per_m3', $validated['isk_per_m3']); MarketSetting::setValue('markup_threshold_pct', $validated['markup_threshold_pct']); MarketSetting::setValue('stock_low_threshold_pct', $validated['stock_low_threshold']); MarketSetting::setValue('discord_webhook_url', $validated['discord_webhook_url']);
        MarketHub::all()->each(fn (MarketHub $hub) => $this->metrics->flushHubCache($hub->id));
        return redirect()->route('seat-importing.settings')->with('success', 'Settings saved.');
    }

    public function searchSystems(Request $request): JsonResponse { $q = $request->get('q'); $results = SolarSystem::where('name', 'like', "%$q%")->limit(15)->get(['system_id as id', 'name as text']); return response()->json(['results' => $results]); }
    public function searchRegions(Request $request): JsonResponse { $q = $request->get('q'); $results = Region::where('name', 'like', "%$q%")->limit(15)->get(['region_id as id', 'name as text']); return response()->json(['results' => $results]); }
    public function searchStructures(Request $request): JsonResponse { $q = $request->get('q'); $results = UniverseStructure::where('name', 'like', "%$q%")->limit(15)->get(['structure_id as id', 'name as text']); return response()->json(['results' => $results]); }

    public function itemDetail(int $typeId): JsonResponse
    {
        try { $sde = DB::connection('sde'); $type = $sde->table('invTypes as t')->leftJoin('invGroups as g', 'g.groupID', '=', 't.groupID')->leftJoin('invCategories as c', 'c.categoryID', '=', 'g.categoryID')->select('t.typeID','t.typeName','t.description','t.volume','t.mass','t.groupID','g.groupName','c.categoryID','c.categoryName')->where('t.typeID', $typeId)->first(); } catch (\Exception) { $type = null; }
        $priceRow = MarketItemData::where('type_id', $typeId)->latest('data_date')->first();
        if (! $type && ! $priceRow) return response()->json(['error' => 'Item not found'], 404);
        return response()->json(['type_id' => $typeId, 'type_name' => $type?->typeName ?? $priceRow?->type_name ?? "Type #{$typeId}", 'group_name' => $type?->groupName ?? '—', 'category_name' => $type?->categoryName ?? '—', 'volume_m3' => $type?->volume ?? $priceRow?->volume_m3 ?? 0, 'description' => $type?->description ?? '', 'jita_sell' => $priceRow?->jita_sell_price ?? 0, 'local_sell' => $priceRow?->local_sell_price ?? 0, 'markup_pct' => $priceRow?->markup_pct ?? 0, 'weekly_profit' => $priceRow?->weekly_profit ?? 0, 'weekly_volume' => $priceRow?->weekly_volume ?? 0, 'current_stock' => $priceRow?->current_stock ?? 0, 'data_date' => $priceRow?->data_date]);
    }

    public function triggerImport(Request $request): JsonResponse
    {
        $this->authorize('market.import');
        $validated = $request->validate(['hub_id' => 'nullable|integer|exists:market_hubs,id', 'source' => 'nullable|string|in:fuzzwork_csv,tycoon_csv']);
        ProcessMarketImport::dispatch($validated['hub_id'] ?? null, $validated['source'] ?? config('seat-importing.import.default_source'), null);
        return response()->json(['message' => 'Import job dispatched.']);
    }

    public function pushToDiscord(Request $request): JsonResponse
    {
        $this->authorize('market.import');
        $request->validate(['text' => 'required|string', 'hub_id' => 'nullable|integer']);
        $webhook = MarketSetting::get('discord_webhook_url');
        if (!$webhook) return response()->json(['error' => 'Discord webhook not configured.'], 422);

        $hubName = "Stocking List";
        $structureName = "N/A";

        if ($request->hub_id) {
            $hub = MarketHub::find($request->hub_id);
            if ($hub) {
                $hubName = $hub->name;
                if ($hub->structure_id) {
                    $structureName = DB::table('universe_structures')->where('structure_id', $hub->structure_id)->value('name') ?? "Unknown Structure";
                } elseif ($hub->solar_system_id) {
                    $structureName = DB::table('solar_systems')->where('system_id', $hub->solar_system_id)->value('name') ?? "Unknown System";
                }
            }
        }

        $payload = [
            'content' => "**Market Resupply List: " . $hubName . "**\n**Location:** " . $structureName . "\n```\n" . $request->text . "\n```",
            'username' => 'SeAT Market Bot',
        ];

        try {
            Http::post($webhook, $payload);
            return response()->json(['success' => 'List pushed to Discord.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to push to Discord.'], 500);
        }
    }
}
