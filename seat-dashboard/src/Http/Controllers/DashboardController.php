<?php

namespace Apokavkos\SeatDashboard\Http\Controllers;

use Seat\Web\Http\Controllers\Controller;
use Seat\Eveapi\Models\Industry\CharacterIndustryJob;
use Seat\Eveapi\Models\Industry\CorporationIndustryJob;
use Seat\Eveapi\Models\Wallet\CorporationWalletBalance;
use Seat\Eveapi\Models\Character\CharacterInfo;
use Illuminate\Support\Facades\DB;
use Seat\Eveapi\Models\Wallet\CharacterWalletBalance;
use Seat\Eveapi\Models\Corporation\CorporationInfo;
use Illuminate\Http\Request;
use Seat\Services\Models\UserSetting;
use Apokavkos\SeatDashboard\Models\SystemTrack;
use Apokavkos\SeatDashboard\Services\IndustryService;
use Seat\Eveapi\Models\Sde\SolarSystem;
use Seat\Eveapi\Models\Sde\StaStation;
use Seat\Eveapi\Models\Universe\UniverseStructure;
use Seat\Eveapi\Models\Sde\Constellation;

class DashboardController extends Controller
{
    public function index(Request $request, IndustryService $industryService)
    {
        $user_id = auth()->user()->id;
        $character_ids = auth()->user()->associatedCharacterIds();

        // Persistent Settings
        if ($request->has('corporation_id')) {
            $val = $request->get('corporation_id');
            UserSetting::updateOrCreate(
                ['user_id' => $user_id, 'name' => 'seat_dashboard_selected_corp'],
                ['value' => ($val === "") ? null : $val]
            );
        }
        if ($request->has('wallet_division')) {
            $val = $request->get('wallet_division');
            if ($val !== null) {
                UserSetting::updateOrCreate(
                    ['user_id' => $user_id, 'name' => 'seat_dashboard_wallet_division'],
                    ['value' => $val]
                );
            }
        }

        $selected_corp_id = UserSetting::where('user_id', $user_id)->where('name', 'seat_dashboard_selected_corp')->value('value');
        $wallet_division = UserSetting::where('user_id', $user_id)->where('name', 'seat_dashboard_wallet_division')->value('value') ?: 1;

        $corporations = CorporationInfo::where('corporation_infos.corporation_id', '>', 2000000)
            ->whereIn('corporation_infos.corporation_id', function($query) use ($character_ids) {
                $query->select('character_affiliations.corporation_id')
                    ->from('character_affiliations')
                    ->join('refresh_tokens', 'character_affiliations.character_id', '=', 'refresh_tokens.character_id')
                    ->whereIn('character_affiliations.character_id', $character_ids);
            })->get();

        $query_character_ids = $character_ids;
        if ($selected_corp_id) {
            $query_character_ids = DB::table('character_affiliations')
                ->where('corporation_id', $selected_corp_id)
                ->whereIn('character_id', $character_ids)
                ->pluck('character_id')
                ->toArray();
        }

        $division_labels = [];
        for ($i = 1; $i <= 7; $i++) { $division_labels[$i] = ($i == 1) ? 'Master' : 'Division ' . $i; }
        if ($selected_corp_id) {
            $db_labels = DB::table('corporation_divisions')->where('corporation_id', $selected_corp_id)->where('type', 'wallet')->pluck('name', 'division')->toArray();
            foreach ($db_labels as $div => $name) { if (!empty($name)) $division_labels[$div] = $name; }
        }

        $char_jobs = CharacterIndustryJob::whereIn('installer_id', $query_character_ids)->where('status', 'active')->get();
        $corp_jobs = CorporationIndustryJob::whereIn('installer_id', $query_character_ids)->where('status', 'active')->get();
        $all_active_jobs = $char_jobs->concat($corp_jobs);
        $grouped_jobs = $all_active_jobs->groupBy('installer_id');

        $wallet_balances = CharacterWalletBalance::whereIn('character_id', $query_character_ids)->get();
        $corp_wallet_balances = CorporationWalletBalance::where('corporation_id', $selected_corp_id)
            ->where('division', $wallet_division)->get();

        $tracked_systems = SystemTrack::all();
        $industry_data = $industryService->getSystemIndustryData($tracked_systems->pluck('system_id')->toArray());

        return view('seat-dashboard::index', [
            'corporations' => $corporations,
            'selected_corp_id' => $selected_corp_id,
            'wallet_division' => $wallet_division,
            'division_labels' => $division_labels,
            'grouped_jobs' => $grouped_jobs,
            'wallet_balances' => $wallet_balances,
            'corp_wallet_balances' => $corp_wallet_balances,
            'industry_data' => $industry_data,
            'tracked_systems' => $tracked_systems,
        ]);
    }

    public function clones()
    {
        $characters = CharacterInfo::with([
            'location.solar_system', 
            'ship.type', 
            'jump_clones.location.solar_system'
        ])->get();
        
        foreach ($characters as $char) {
            // Resolve current location
            if ($char->location) {
                $loc = $char->location;
                if ($loc->structure_id) {
                    $char->resolved_location_name = UniverseStructure::where('structure_id', $loc->structure_id)->value('name') ?? 'Unknown Structure';
                } elseif ($loc->station_id) {
                    $station = StaStation::where('stationID', $loc->station_id)->first();
                    $system_name = SolarSystem::where('system_id', $station->solarSystemID)->value('name') ?? 'Unknown';
                    $char->resolved_location_name = ($station->stationName ?? 'Unknown Station') . " (" . $system_name . ")";
                } else {
                    $char->resolved_location_name = $loc->solar_system->name ?? 'Unknown System';
                }
            } else {
                $char->resolved_location_name = 'Unknown';
            }
            
            // Resolve jump clones
            foreach ($char->jump_clones as $clone) {
                $lid = $clone->location_id;
                if ($lid > 1000000000000) {
                    $clone->resolved_location_name = UniverseStructure::where('structure_id', $lid)->value('name') ?? 'Unknown Structure';
                } elseif ($lid >= 60000000 && $lid < 70000000) {
                    $station = StaStation::where('stationID', $lid)->first();
                    if ($station) {
                        $system_name = SolarSystem::where('system_id', $station->solarSystemID)->value('name') ?? 'Unknown';
                        $clone->resolved_location_name = ($station->stationName ?? 'Unknown Station') . " (" . $system_name . ")";
                    } else {
                        $clone->resolved_location_name = "NPC Station #{$lid}";
                    }
                } else {
                    $clone->resolved_location_name = SolarSystem::where('system_id', $lid)->value('name') ?? "System #{$lid}";
                }
            }
        }

        return view('seat-dashboard::clones', compact('characters'));
    }

    public function addSystem(Request $request)
    {
        $request->validate(['system_name' => 'required|string']);
        $system = SolarSystem::where('name', $request->system_name)->first();
        if (!$system) return redirect()->back()->with('error', 'System not found.');
        SystemTrack::updateOrCreate(['system_id' => $system->system_id], ['system_name' => $system->name]);
        return redirect()->back()->with('success', "Now tracking {$system->name}");
    }

    public function removeSystem($id) { SystemTrack::where('system_id', $id)->delete(); return redirect()->back()->with('success', 'System removed.'); }
}
