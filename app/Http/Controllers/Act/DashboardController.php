<?php

namespace App\Http\Controllers\Act;

use App\Http\Controllers\Controller;
use App\Models\Act\Activity;
use App\Models\Act\Division;
use App\Models\Act\Team;
use App\Services\Act\RosterService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        [$ctxDate, $ctxShift] = RosterService::context();
        try { $boardDate = $request->filled('date') ? Carbon::parse($request->get('date'))->toDateString() : $ctxDate; } catch (\Exception $e) { $boardDate = $ctxDate; }
        $boardShift = in_array((int) $request->get('shift'), [1, 2]) ? (int) $request->get('shift') : $ctxShift;
        $hasTeams = Schema::hasTable('act_teams');
        $boardTeams = $hasTeams
            ? Team::with(['division', 'members', 'plans.device', 'plans.location', 'plans.tool', 'plans.activity'])
                ->where('team_date', $boardDate)->where('shift', $boardShift)->orderBy('id')->get()->groupBy('division_id')
            : collect();
        $boardDivisions = Division::where('is_active', 1)->orderBy('id')->get();

        $today      = \App\Services\Act\RosterService::today();
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $monthEnd   = $today->copy()->endOfMonth()->toDateString();

        $chains = Activity::activeChains();

        $cards = [
            'today'        => Activity::whereDate('activity_date', $today->toDateString())->count(),
            'finish_today' => Activity::whereDate('activity_date', $today->toDateString())->where('status', 'finish')->count(),
            'pending'      => $chains->count(),
            'closed_month' => Activity::whereIn('status', ['finish', 'closed'])
                                ->whereBetween('activity_date', [$monthStart, $monthEnd])->count(),
        ];

        // 7 hari terakhir: selesai (finish+closed) vs belum selesai (pending+continue)
        $from = $today->copy()->subDays(6);
        $raw  = Activity::selectRaw('activity_date as d, status, COUNT(*) as c')
            ->whereBetween('activity_date', [$from->toDateString(), $today->toDateString()])
            ->groupBy('activity_date', 'status')->get();

        $labels = []; $done = []; $open = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $from->copy()->addDays($i);
            $key = $day->toDateString();
            $labels[] = $day->format('d M');
            $done[]   = (int) $raw->where('d', $key)->whereIn('status', ['finish', 'closed'])->sum('c');
            $open[]   = (int) $raw->where('d', $key)->whereIn('status', ['pending', 'continue'])->sum('c');
        }

        $perDivision = Activity::selectRaw('act_divisions.name as name, COUNT(*) as total')
            ->join('act_divisions', 'act_divisions.id', '=', 'act_activities.division_id')
            ->whereBetween('act_activities.activity_date', [$monthStart, $monthEnd])
            ->groupBy('act_divisions.name')->pluck('total', 'name');

        $threshold  = $this->threshold();
        $topPending = $chains->sortByDesc('age_days')->take(5);

        return view('act.dashboard', compact('cards', 'labels', 'done', 'open', 'perDivision', 'topPending', 'threshold', 'hasTeams', 'boardDate', 'boardShift', 'boardTeams', 'boardDivisions'));
    }

    private function threshold(): int
    {
        $v = (int) DB::table('act_settings')->where('key', 'pending_age_threshold')->value('value');
        return $v > 0 ? $v : 3;
    }
}
