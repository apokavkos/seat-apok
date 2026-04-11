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
use Seat\Eveapi\Models\Sde\Constellation;

class DashboardController extends Controller
{
    public function index(Request $request, IndustryService $industryService)
    {
        $user_id = auth()->user()->id;
        $character_ids = auth()->user()->associatedCharacterIds();

        // 1. Persistent Settings
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

        // 2. Get corporations
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

        // Get Division Names
        $division_labels = [];
        for ($i = 1; $i <= 7; $i++) {
            $division_labels[$i] = ($i == 1) ? 'Master' : 'Division ' . $i;
        }

        if ($selected_corp_id) {
            $db_labels = DB::table('corporation_divisions')
                ->where('corporation_id', $selected_corp_id)
                ->where('type', 'wallet')
                ->pluck('name', 'division')
                ->toArray();
            
            foreach ($db_labels as $div => $name) {
                if (!empty($name)) {
                    $division_labels[$div] = $name;
                }
            }
        }

        // Industry Jobs
        $char_jobs = CharacterIndustryJob::whereIn('installer_id', $query_character_ids)
            ->where('status', 'active')->select('installer_id', 'activity_id')->get();
        $corp_jobs = CorporationIndustryJob::whereIn('installer_id', $query_character_ids)
            ->where('status', 'active')->select('installer_id', 'activity_id')->get();
        $all_active_jobs = $char_jobs->concat($corp_jobs);
        $grouped_jobs = $all_active_jobs->groupBy('installer_id');

        $industry_jobs_totals = $all_active_jobs->groupBy('activity_id')->map(function($jobs) {
            return (object) ['activity_id' => $jobs->first()->activity_id, 'count' => $jobs->count()];
        });

        $activity_mapping = [1 => 'Manufacturing', 3 => 'Time Efficiency', 4 => 'Material Efficiency', 5 => 'Copying', 8 => 'Invention', 9 => 'Reactions'];

        // Wallet Balances
        $wallet_balances = CorporationWalletBalance::where('corporation_wallet_balances.division', $wallet_division)
            ->when($selected_corp_id, function($query) use ($selected_corp_id) {
                return $query->where('corporation_wallet_balances.corporation_id', $selected_corp_id);
            }, function($query) use ($character_ids) {
                return $query->whereIn('corporation_wallet_balances.corporation_id', function($q) use ($character_ids) {
                    $q->select('character_affiliations.corporation_id')
                      ->from('character_affiliations')
                      ->where('corporation_id', '>', 2000000)
                      ->whereIn('character_affiliations.character_id', $character_ids);
                });
            })
            ->join('corporation_infos', 'corporation_wallet_balances.corporation_id', '=', 'corporation_infos.corporation_id')
            ->leftJoin('corporation_divisions', function($join) {
                $join->on('corporation_wallet_balances.corporation_id', '=', 'corporation_divisions.corporation_id')
                     ->on('corporation_wallet_balances.division', '=', 'corporation_divisions.division')
                     ->where('corporation_divisions.type', '=', 'wallet');
            })
            ->select('corporation_wallet_balances.*', 'corporation_infos.name as corp_name', 'corporation_divisions.name as division_name')
            ->get();

        $character_wallets = CharacterInfo::whereIn('character_infos.character_id', $query_character_ids)
            ->leftJoin('character_wallet_balances', 'character_infos.character_id', '=', 'character_wallet_balances.character_id')
            ->select('character_infos.character_id', DB::raw('COALESCE(character_wallet_balances.balance, 0) as balance'), 'character_infos.name as character_name')
            ->get();

        $char_skills = DB::table('character_skills')
            ->whereIn('character_id', $query_character_ids)
            ->whereIn('skill_id', [3387, 24625, 3406, 24624, 45746, 45748])
            ->get()->groupBy('character_id');

        $total_char_isk = 0;
        $summary = ['manu_used' => 0, 'manu_total' => 0, 'science_used' => 0, 'science_total' => 0, 'reactions_used' => 0, 'reactions_total' => 0];

        foreach ($character_wallets as $wallet) {
            $total_char_isk += $wallet->balance;
            $wallet->character = (object) ['character_id' => $wallet->character_id, 'name' => $wallet->character_name];
            
            $c_skills = $char_skills->get($wallet->character_id, collect());
            $c_jobs = $grouped_jobs->get($wallet->character_id, collect());
            $get_skill = function($id) use ($c_skills) {
                $s = $c_skills->where('skill_id', $id)->first();
                return $s ? $s->active_skill_level : 0;
            };

            $m_t = 1 + $get_skill(3387) + $get_skill(24625);
            $m_u = $c_jobs->where('activity_id', 1)->count();
            $wallet->manu_slots = "$m_u / $m_t";
            $summary['manu_used'] += $m_u; $summary['manu_total'] += $m_t;

            $s_t = 1 + $get_skill(3406) + $get_skill(24624);
            $s_u = $c_jobs->whereIn('activity_id', [3, 4, 5, 8])->count();
            $wallet->science_slots = "$s_u / $s_t";
            $summary['science_used'] += $s_u; $summary['science_total'] += $s_t;

            $r_t = 1 + $get_skill(45746) + $get_skill(45748);
            $r_u = $c_jobs->where('activity_id', 9)->count();
            $wallet->reactions_slots = "$r_u / $r_t";
            $summary['reactions_used'] += $r_u; $summary['reactions_total'] += $r_t;
        }

        // 3. System Cost Indexes
        $tracked_systems = SystemTrack::where('user_id', $user_id)
            ->with('solar_system')
            ->orderBy('sort_order', 'asc')
            ->get();
        $all_indexes = $industryService->getSystemCostIndexes();
        
        foreach($tracked_systems as $track) {
            $id = $track->solar_system_id;
            $track->indexes = $all_indexes[$id] ?? [];
        }

        return view('seat-dashboard::dashboard.index', compact(
            'industry_jobs_totals', 'activity_mapping', 'wallet_balances', 
            'character_wallets', 'corporations', 'selected_corp_id', 
            'wallet_division', 'division_labels', 'total_char_isk', 'summary',
            'tracked_systems'
        ));
    }

    public function searchSystems(Request $request)
    {
        $query = $request->get('q');
        if (strlen($query) < 3) {
            return response()->json(['results' => []]);
        }

        $systems = SolarSystem::where('name', 'like', "%$query%")
            ->limit(10)
            ->get(['system_id as id', 'name as text'])
            ->map(function($item) {
                $item->type = 'system';
                $item->text = $item->text . ' (System)';
                return $item;
            });

        $constellations = Constellation::where('name', 'like', "%$query%")
            ->limit(5)
            ->get(['constellation_id as id', 'name as text'])
            ->map(function($item) {
                $item->type = 'constellation';
                $item->text = $item->text . ' (Constellation)';
                return $item;
            });

        return response()->json(['results' => $systems->concat($constellations)]);
    }

    public function addSystem(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'type' => 'required|string|in:system,constellation'
        ]);
        
        $user_id = auth()->user()->id;
        $max_order = SystemTrack::where('user_id', $user_id)->max('sort_order') ?? 0;

        if ($request->type === 'system') {
            SystemTrack::firstOrCreate([
                'user_id' => $user_id,
                'solar_system_id' => $request->id
            ], ['sort_order' => $max_order + 1]);
        } else {
            // Constellation
            $systems = SolarSystem::where('constellation_id', $request->id)->pluck('system_id');
            foreach ($systems as $sys_id) {
                SystemTrack::firstOrCreate([
                    'user_id' => $user_id,
                    'solar_system_id' => $sys_id
                ], ['sort_order' => ++$max_order]);
            }
        }

        return redirect()->back()->with('success', 'Added to tracking.');
    }

    public function removeSystem($id)
    {
        SystemTrack::where('user_id', auth()->user()->id)
            ->where('id', $id)
            ->delete();

        return redirect()->back()->with('success', 'System removed from tracking.');
    }

    public function reorderSystems(Request $request)
    {
        $request->validate(['order' => 'required|array']);
        
        foreach ($request->order as $index => $id) {
            SystemTrack::where('user_id', auth()->user()->id)
                ->where('id', $id)
                ->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }
}
