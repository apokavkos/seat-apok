<?php

namespace Apokavkos\SeatDashboard\Services;

use Seat\Services\Contracts\EsiClient;
use Illuminate\Support\Facades\Cache;

class IndustryService
{
    public function getSystemCostIndexes()
    {
        return Cache::remember('seat-dashboard.industry.systems', 3600, function () {
            try {
                // Resolve the configured EsiClient from SeAT
                $client = app(EsiClient::class);
                $response = $client->invoke('get', '/industry/systems/');
                
                // SeAT 5 returns an EsiResponse object, we need the body data
                $data = $response->getBody();
                
                $indexes = [];
                foreach ($data as $system) {
                    $system_id = $system->solar_system_id;
                    $indices = [];
                    foreach ($system->cost_indices as $index) {
                        $indices[$index->activity] = $index->cost_index;
                    }
                    $indexes[$system_id] = $indices;
                }
                return $indexes;
            } catch (\Exception $e) {
                logger()->error("Failed to fetch ESI industry systems: " . $e->getMessage());
                return [];
            }
        });
    }
}
