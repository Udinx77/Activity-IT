<?php

namespace App\Http\Controllers\Act;

use App\Http\Controllers\Controller;
use App\Models\Act\Activity;
use App\Models\Act\ActivityLog;
use App\Models\Act\Division;
use App\Services\Act\MasterResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ActivityController extends Controller
{
    /** Daily / Weekly / Monthly: daftar + ringkasan periode. */
    public function index(Request $request)
    {
        $period = in_array($request->get('period'), ['daily', 'weekly', 'monthly']) ? $request->get('period') : 'daily';
        try {
            $d = Carbon::parse($request->get('date', \App\Services\Act\RosterService::today()->toDateString()));
        } catch (\Exception $e) {
            $d = \App\Services\Act\RosterService::today();
        }

        if ($period === 'daily') {
            $start = $d->copy()->startOfDay();   $end  = $d->copy()->endOfDay();
            $prev  = $d->copy()->subDay();       $next = $d->copy()->addDay();
        } elseif ($period === 'weekly') {
            $start = $d->copy()->startOfWeek(Carbon::MONDAY); $end = $d->copy()->endOfWeek(Carbon::SUNDAY);
            $prev  = $d->copy()->subWeek();      $next = $d->copy()->addWeek();
        } else {
            $start = $d->copy()->startOfMonth(); $end  = $d->copy()->endOfMonth();
            $prev  = $d->copy()->subMonthNoOverflow(); $next = $d->copy()->addMonthNoOverflow();
        }

        $rows = Activity::with(['division', 'shift', 'device', 'location', 'supervisor', 'tool', 'technicians'])
            ->whereBetween('activity_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('activity_date')->orderBy('id')->get();

        $metrics = [
            'total'  => $rows->count(),
            'finish' => $rows->where('status', 'finish')->count(),
            'open'   => $rows->whereIn('status', ['pending', 'continue'])->count(),
            'closed' => $rows->where('status', 'closed')->count(),
        ];
        $count = function ($g) { return $g->count(); };
        $perDivision   = $rows->groupBy(function ($r) { return optional($r->division)->name ?: '-'; })->map($count)->sortDesc();
        $perSupervisor = $rows->groupBy(function ($r) { return optional($r->supervisor)->name ?: '-'; })->map($count)->sortDesc();
        $perDevice     = $rows->groupBy(function ($r) { return optional($r->device)->name ?: '-'; })->map($count)->sortDesc();
        $topProblem    = $rows->filter(function ($r) { return trim((string) $r->problem) !== ''; })
            ->groupBy(function ($r) { return strtolower(trim($r->problem)); })->map($count)->sortDesc()->take(5);

        return view('act.activity.index', [
            'period' => $period, 'date' => $d->toDateString(), 'start' => $start, 'end' => $end,
            'prev' => $prev->toDateString(), 'next' => $next->toDateString(),
            'rows' => $rows, 'metrics' => $metrics,
            'perDivision' => $perDivision, 'perSupervisor' => $perSupervisor,
            'perDevice' => $perDevice, 'topProblem' => $topProblem,
        ]);
    }

    public function create()
    {
        $activity = new Activity(['plan' => 'unschedule', 'status' => 'finish', 'activity_date' => \App\Services\Act\RosterService::today()]);
        return view('act.activity.form', array_merge(Activity::lookups(), [
            'activity' => $activity, 'isEdit' => false, 'selectedTech' => [],
        ]));
    }

    public function store(Request $request)
    {
        $data = MasterResolver::apply($this->validated($request));
        $tech = Arr::pull($data, 'technician_ids', []);

        $division       = Division::findOrFail($data['division_id']);
        $data['status'] = 'finish'; // sheet/menu ini khusus job yang selesai dalam 1 shift
        $data['code']   = Activity::nextCode($division->prefix);
        $data['created_by'] = auth()->id();

        $activity = DB::transaction(function () use ($data, $tech) {
            $a = Activity::create($data);
            $a->technicians()->sync($tech);
            ActivityLog::create(['activity_id' => $a->id, 'user_id' => auth()->id(), 'action' => 'create', 'changes' => $data]);
            return $a;
        });

        return redirect()->route('act.activity.index', ['period' => 'daily', 'date' => $activity->activity_date->toDateString()])
            ->with('success', 'Activity ' . $activity->code . ' berhasil disimpan.');
    }

    public function show(Activity $activity)
    {
        $activity->load(['division', 'shift', 'device', 'location', 'supervisor', 'tool', 'technicians', 'logs.user']);
        $chain = ($activity->parent_code || in_array($activity->status, ['pending', 'continue', 'closed']))
            ? $activity->chain() : collect();
        return view('act.activity.show', compact('activity', 'chain'));
    }

    public function edit(Activity $activity)
    {
        $activity->load('technicians');
        return view('act.activity.form', array_merge(Activity::lookups(), [
            'activity' => $activity, 'isEdit' => true,
            'selectedTech' => $activity->technicians->pluck('id')->all(),
        ]));
    }

    public function update(Request $request, Activity $activity)
    {
        $data = MasterResolver::apply($this->validated($request));
        $data['status'] = $request->validate(['status' => 'required|in:finish,pending,continue,closed'])['status'];
        $tech = Arr::pull($data, 'technician_ids', []);
        $old  = Arr::only($activity->getRawOriginal(), array_keys($data));

        DB::transaction(function () use ($activity, $data, $tech, $old) {
            $activity->update($data);
            $activity->technicians()->sync($tech);
            ActivityLog::create(['activity_id' => $activity->id, 'user_id' => auth()->id(), 'action' => 'update',
                'changes' => ['old' => $old, 'new' => $data]]);
        });

        return redirect()->route('act.activity.index', ['period' => 'daily', 'date' => $activity->fresh()->activity_date->toDateString()])
            ->with('success', 'Activity ' . $activity->code . ' berhasil diperbarui.');
    }

    public function destroy(Activity $activity)
    {
        ActivityLog::create(['activity_id' => $activity->id, 'user_id' => auth()->id(), 'action' => 'delete', 'changes' => null]);
        $activity->delete(); // soft delete
        return redirect()->route('act.activity.index', ['period' => 'daily', 'date' => $activity->activity_date->toDateString()])
            ->with('success', 'Activity ' . $activity->code . ' dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'division_id'     => 'required|exists:act_divisions,id',
            'activity_date'   => 'required|date',
            'shift_id'        => 'nullable|exists:act_shifts,id',
            'plan'            => 'required|in:schedule,unschedule',
            'start_time'      => 'nullable|date_format:H:i',
            'finish_time'     => 'nullable|date_format:H:i',
            'device_id'       => 'nullable|exists:act_devices,id',
            'device_name'     => 'nullable|string|max:255',
            'unit'            => 'nullable|string|max:50',
            'location_id'     => 'nullable|exists:act_locations,id',
            'location_name'   => 'nullable|string|max:255',
            'supervisor_id'   => 'nullable|exists:act_supervisors,id',
            'tool_id'         => 'nullable|exists:act_tools,id',
            'problem'         => 'nullable|string',
            'troubleshoot'    => 'nullable|string',
            'remark'          => 'nullable|string',
            'technician_ids'  => 'nullable|array',
            'technician_ids.*' => 'integer|exists:act_technicians,id',
        ]);
    }
}
