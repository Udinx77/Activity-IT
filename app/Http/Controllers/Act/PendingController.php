<?php

namespace App\Http\Controllers\Act;

use App\Http\Controllers\Controller;
use App\Models\Act\Activity;
use App\Models\Act\ActivityLog;
use App\Models\Act\Division;
use App\Models\Act\Shift;
use App\Services\Act\MasterResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PendingController extends Controller
{
    /** Rantai pending yang belum Closed. */
    public function index()
    {
        $chains    = Activity::activeChains()->sortByDesc('age_days')->values();
        $threshold = $this->threshold();
        return view('act.pending.index', compact('chains', 'threshold'));
    }

    /** Job baru yang tidak selesai dalam 1 shift -> baris pertama rantai (ID: PD###). */
    public function create()
    {
        $activity = new Activity(['plan' => 'unschedule', 'status' => 'pending', 'activity_date' => \App\Services\Act\RosterService::today()]);
        return view('act.pending.form', array_merge(Activity::lookups(), ['activity' => $activity]));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'division_id'      => 'required|exists:act_divisions,id',
            'activity_date'    => 'required|date',
            'shift_id'         => 'nullable|exists:act_shifts,id',
            'plan'             => 'required|in:schedule,unschedule',
            'start_time'       => 'nullable|date_format:H:i',
            'finish_time'      => 'nullable|date_format:H:i',
            'device_id'        => 'nullable|exists:act_devices,id',
            'device_name'      => 'nullable|string|max:255',
            'unit'             => 'nullable|string|max:50',
            'location_id'      => 'nullable|exists:act_locations,id',
            'location_name'    => 'nullable|string|max:255',
            'supervisor_id'    => 'nullable|exists:act_supervisors,id',
            'tool_id'          => 'nullable|exists:act_tools,id',
            'problem'          => 'required|string',
            'troubleshoot'     => 'nullable|string',
            'remark'           => 'nullable|string',
            'technician_ids'   => 'nullable|array',
            'technician_ids.*' => 'integer|exists:act_technicians,id',
        ]);
        $data = MasterResolver::apply($data);
        $tech = Arr::pull($data, 'technician_ids', []);
        $data['status']     = 'pending';
        $data['parent_code'] = null;
        $data['code']       = Activity::nextCode('PD');
        $data['created_by'] = auth()->id();

        $a = DB::transaction(function () use ($data, $tech) {
            $a = Activity::create($data);
            $a->technicians()->sync($tech);
            ActivityLog::create(['activity_id' => $a->id, 'user_id' => auth()->id(), 'action' => 'create', 'changes' => $data]);
            return $a;
        });

        return redirect()->route('act.pending.index')->with('success', 'Job pending ' . $a->code . ' dicatat.');
    }

    public function continueForm(Activity $activity)
    {
        $chain = $activity->chain();
        if ($chain->contains('status', 'closed')) {
            return redirect()->route('act.pending.index')->with('error', 'Rantai ' . $activity->root_code . ' sudah Closed, tidak bisa dilanjutkan.');
        }
        $root = $chain->firstWhere('code', $activity->root_code) ?: $chain->first();
        $last = $chain->last();
        $shifts = Shift::where('is_active', 1)->orderBy('id')->get();
        return view('act.pending.continue', compact('activity', 'chain', 'root', 'last', 'shifts'));
    }

    /** Lanjutan shift/hari berikutnya: ID baru (PD###) + ID Induk = root rantai. */
    public function storeContinue(Request $request, Activity $activity)
    {
        $chain = $activity->chain();
        if ($chain->contains('status', 'closed')) {
            return redirect()->route('act.pending.index')->with('error', 'Rantai ini sudah Closed.');
        }
        $last = $chain->last();
        $root = $chain->firstWhere('code', $activity->root_code) ?: $chain->first();

        $data = $request->validate([
            'activity_date'    => 'required|date|after_or_equal:' . $last->activity_date->toDateString(),
            'shift_id'         => 'nullable|exists:act_shifts,id',
            'start_time'       => 'nullable|date_format:H:i',
            'finish_time'      => 'nullable|date_format:H:i',
            'problem'          => 'nullable|string',
            'troubleshoot'     => 'required|string',
            'status'           => 'required|in:continue,closed',
            'remark'           => 'nullable|string',
            'technician_ids'   => 'nullable|array',
            'technician_ids.*' => 'integer|exists:act_technicians,id',
        ]);
        $tech = $request->input('technician_ids') ?: $last->technicians->pluck('id')->all();

        $new = DB::transaction(function () use ($data, $root, $tech) {
            $new = Activity::create([
                'code'          => Activity::nextCode('PD'),
                'division_id'   => $root->division_id,
                'parent_code'   => $root->code,
                'activity_date' => $data['activity_date'],
                'shift_id'      => $data['shift_id'] ?? null,
                'plan'          => $root->plan,
                'start_time'    => $data['start_time'] ?? null,
                'finish_time'   => $data['finish_time'] ?? null,
                'device_id'     => $root->device_id,
                'unit'          => $root->unit,
                'location_id'   => $root->location_id,
                'supervisor_id' => $root->supervisor_id,
                'tool_id'       => $root->tool_id,
                'problem'       => ($data['problem'] ?? null) ?: $root->problem,
                'troubleshoot'  => $data['troubleshoot'],
                'status'        => $data['status'],
                'remark'        => $data['remark'] ?? null,
                'created_by'    => auth()->id(),
            ]);
            $new->technicians()->sync($tech);
            ActivityLog::create(['activity_id' => $new->id, 'user_id' => auth()->id(),
                'action' => $data['status'] === 'closed' ? 'close' : 'continue', 'changes' => ['parent' => $root->code]]);
            return $new;
        });

        $msg = $data['status'] === 'closed'
            ? 'Rantai ' . $root->code . ' ditutup dengan ' . $new->code . '. Job masuk daftar Closed.'
            : 'Lanjutan ' . $new->code . ' (Induk: ' . $root->code . ') dicatat.';
        return redirect()->route('act.pending.index')->with('success', $msg);
    }

    public function timeline(Activity $activity)
    {
        $chain = $activity->chain()->load(Activity::v2() ? ['shift', 'technicians', 'troubleshoots'] : ['shift', 'technicians']);
        $root  = $chain->firstWhere('code', $activity->root_code) ?: $chain->first();
        $root->load(['division', 'device', 'location', 'supervisor', 'tool']);
        $isClosed = $chain->contains('status', 'closed');
        $age = (int) $chain->first()->activity_date->diffInDays($isClosed ? $chain->firstWhere('status', 'closed')->activity_date : \App\Services\Act\RosterService::today());
        return view('act.pending.timeline', compact('chain', 'root', 'isClosed', 'age'));
    }

    private function threshold(): int
    {
        $v = (int) DB::table('act_settings')->where('key', 'pending_age_threshold')->value('value');
        return $v > 0 ? $v : 3;
    }
}
