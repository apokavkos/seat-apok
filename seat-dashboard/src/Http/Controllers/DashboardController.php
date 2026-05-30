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

        // Calculate slots used
        $manu_used = 0;
        $science_used = 0;
        $reactions_used = 0;
        foreach ($all_active_jobs as $job) {
            if ($job->activity_id == 1) {
                $manu_used++;
            } elseif (in_array($job->activity_id, [3, 4, 5, 7, 8])) {
                $science_used++;
            } elseif ($job->activity_id == 9) {
                $reactions_used++;
            }
        }

        // Calculate total slots allowed based on character skills and attach them to character wallets
        $skills = DB::table('character_skills')
            ->whereIn('character_id', $query_character_ids)
            ->whereIn('skill_id', [3387, 24625, 3406, 24624, 45746, 45748])
            ->get()
            ->groupBy('character_id');

        $characters = CharacterInfo::whereIn('character_id', $query_character_ids)->get()->keyBy('character_id');
        $wallet_balances = CharacterWalletBalance::whereIn('character_id', $query_character_ids)->get();
        
        $manu_total = 0;
        $science_total = 0;
        $reactions_total = 0;
        $total_char_isk = $wallet_balances->sum('balance');

        foreach ($wallet_balances as $wallet) {
            $char_id = $wallet->character_id;
            $wallet->character = $characters->get($char_id);
            $char_skills = $skills->get($char_id) ?: collect();
            
            $mass_prod = $char_skills->where('skill_id', 3387)->first()->active_skill_level ?? 0;
            $adv_mass_prod = $char_skills->where('skill_id', 24625)->first()->active_skill_level ?? 0;
            
            $lab_op = $char_skills->where('skill_id', 3406)->first()->active_skill_level ?? 0;
            $adv_lab_op = $char_skills->where('skill_id', 24624)->first()->active_skill_level ?? 0;
            
            $reactions = $char_skills->where('skill_id', 45746)->first()->active_skill_level ?? 0;
            $mass_reactions = $char_skills->where('skill_id', 45748)->first()->active_skill_level ?? 0;

            $wallet->manu_slots = 1 + $mass_prod + $adv_mass_prod;
            $wallet->science_slots = 1 + $lab_op + $adv_lab_op;
            $wallet->reactions_slots = 1 + $reactions + $mass_reactions;

            $manu_total += $wallet->manu_slots;
            $science_total += $wallet->science_slots;
            $reactions_total += $wallet->reactions_slots;
        }

        $summary = [
            'manu_used' => $manu_used,
            'manu_total' => $manu_total,
            'science_used' => $science_used,
            'science_total' => $science_total,
            'reactions_used' => $reactions_used,
            'reactions_total' => $reactions_total,
        ];

        $character_wallets = $wallet_balances;

        // Group active jobs by activity_id to calculate totals
        $industry_jobs_totals = $all_active_jobs->groupBy('activity_id')->map(function ($group, $activity_id) {
            return (object)[
                'activity_id' => $activity_id,
                'count' => $group->count()
            ];
        })->values();

        $activity_mapping = [
            1 => 'Manufacturing',
            3 => 'Researching Time Efficiency',
            4 => 'Researching Material Efficiency',
            5 => 'Copying',
            7 => 'Reverse Engineering',
            8 => 'Invention',
            9 => 'Reactions',
        ];

        $corp_wallet_balances = CorporationWalletBalance::where('corporation_id', $selected_corp_id)
            ->where('division', $wallet_division)->get();

        $tracked_systems = SystemTrack::where('user_id', $user_id)->orderBy('sort_order', 'asc')->get();
        $cost_indexes = $industryService->getSystemCostIndexes();
        foreach ($tracked_systems as $system) {
            $system->indexes = $cost_indexes[$system->solar_system_id] ?? [];
        }

        return view('seat-dashboard::dashboard.index', [
            'corporations' => $corporations,
            'selected_corp_id' => $selected_corp_id,
            'wallet_division' => $wallet_division,
            'division_labels' => $division_labels,
            'grouped_jobs' => $grouped_jobs,
            'wallet_balances' => $wallet_balances,
            'corp_wallet_balances' => $corp_wallet_balances,
            'tracked_systems' => $tracked_systems,
            'summary' => $summary,
            'total_char_isk' => $total_char_isk,
            'character_wallets' => $character_wallets,
            'industry_jobs_totals' => $industry_jobs_totals,
            'activity_mapping' => $activity_mapping,
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

        $user_id = auth()->user()->id;
        SystemTrack::updateOrCreate([
            'user_id' => $user_id,
            'solar_system_id' => $system->system_id
        ], [
            'sort_order' => SystemTrack::where('user_id', $user_id)->max('sort_order') + 1
        ]);

        return redirect()->back()->with('success', "Now tracking {$system->name}");
    }

    public function removeSystem($id)
    {
        $user_id = auth()->user()->id;
        SystemTrack::where('user_id', $user_id)->where('id', $id)->delete();
        return redirect()->back()->with('success', 'System removed.');
    }

    public function reorderSystems(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|integer',
        ]);

        $user_id = auth()->user()->id;

        foreach ($request->order as $index => $id) {
            SystemTrack::where('user_id', $user_id)->where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }
}
