<?php

namespace App\Http\Controllers\Act;

use App\Http\Controllers\Controller;
use App\Models\Act\Activity;
use App\Models\Act\ActivityLog;
use App\Models\Act\Plan;
use App\Models\Act\Shift;
use App\Models\Act\Supervisor;
use App\Models\Act\Team;
use App\Models\Act\Technician;
use App\Models\Act\Troubleshoot;
use App\Services\Act\MasterResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plan (ceklis problem) per tim + follow-up troubleshoot.
 *  Plan baru : Finish -> activity ONB###/OP###/AL###/IF### ; Pending -> PD### (root rantai)
 *  Plan bawaan dari Pending : Continue / Closed -> PD### baru, ID Induk = root rantai
 */
class PlanController extends Controller
{
    public function create(Team $team)
    {
        return view('act.plans.form', array_merge(Activity::lookups(), ['team' => $team->load('division'), 'plan' => new Plan(), 'isEdit' => false]));
    }

    public function store(Request $request, Team $team)
    {
        $data = $this->planData($request);
        $data['team_id'] = $team->id;
        $data['seq'] = (int) $team->plans()->max('seq') + 1;
        $data['created_by'] = auth()->id();
        Plan::create($data);
        return $this->toBoard($team)->with('success', 'Plan ditambahkan ke ' . $team->name . '.');
    }

    public function edit(Plan $plan)
    {
        if (!$plan->isOpen()) { return back()->with('error', 'Plan yang sudah di-follow-up tidak bisa diedit. Ubah lewat follow-up.'); }
        return view('act.plans.form', array_merge(Activity::lookups(), ['team' => $plan->team->load('division'), 'plan' => $plan, 'isEdit' => true]));
    }

    public function update(Request $request, Plan $plan)
    {
        if (!$plan->isOpen()) { return back()->with('error', 'Plan yang sudah di-follow-up tidak bisa diedit.'); }
        $plan->update($this->planData($request));
        return $this->toBoard($plan->team)->with('success', 'Plan diperbarui.');
    }

    public function destroy(Plan $plan)
    {
        if (!$plan->isOpen()) { return back()->with('error', 'Plan yang sudah di-follow-up tidak bisa dihapus. Batalkan hasilnya dulu.'); }
        $team = $plan->team; $plan->delete();
        return $this->toBoard($team)->with('success', 'Plan dihapus.');
    }

    // ---------------- bawa pending ke tim ----------------
    public function carryForm(Team $team)
    {
        $planned = Plan::where('status', 'open')->whereNotNull('carry_root_code')->pluck('carry_root_code')->all();
        $chains = Activity::activeChains()->filter(function ($c) use ($planned) { return !in_array($c->root_code, $planned); })
            ->sortByDesc('age_days')->values();
        foreach ($chains as $c) { $c->last->load('troubleshoots'); }
        return view('act.plans.carry', ['team' => $team->load('division'), 'chains' => $chains]);
    }

    public function carryStore(Request $request, Team $team)
    {
        $roots = (array) $request->input('root_codes', []);
        if (!$roots) { return back()->with('error', 'Pilih minimal satu pending.'); }
        $made = 0;
        foreach ($roots as $code) {
            $root = Activity::where('code', $code)->first();
            if (!$root || $root->chain()->contains('status', 'closed')) { continue; }
            if (Plan::where('status', 'open')->where('carry_root_code', $code)->exists()) { continue; }
            $last = $root->chain()->last()->load('troubleshoots');
            $next = $last->lines('next_plan');
            Plan::create([
                'team_id' => $team->id, 'seq' => (int) $team->plans()->max('seq') + 1, 'title' => $root->problem ?: $code,
                'device_id' => $root->device_id, 'unit' => $root->unit, 'location_id' => $root->location_id, 'tool_id' => $root->tool_id,
                'note' => $next ? implode("\n", $next) : null, 'carry_root_code' => $code, 'created_by' => auth()->id(),
            ]);
            $made++;
        }
        return $this->toBoard($team)->with($made ? 'success' : 'error', $made ? $made . ' pending dibawa ke ' . $team->name . '.' : 'Tidak ada yang ditambahkan (sudah Closed atau sudah diplankan).');
    }

    // ---------------- follow-up troubleshoot ----------------
    public function followup(Plan $plan)
    {
        $plan->load(['team.division', 'device', 'location', 'tool', 'activity.troubleshoots']);
        $isCarry = $plan->carry_root_code !== null;
        $chain = $isCarry ? (optional(Activity::where('code', $plan->carry_root_code)->first())->chain() ?: collect()) : collect();
        foreach ($chain as $c) { $c->load('troubleshoots'); }
        return view('act.plans.followup', [
            'plan' => $plan, 'team' => $plan->team, 'isCarry' => $isCarry, 'chain' => $chain,
            'act' => $plan->activity, 'editing' => $plan->activity_id !== null,
        ]);
    }

    public function saveFollowup(Request $request, Plan $plan)
    {
        $plan->load(['team.members', 'team.division', 'activity']);
        $team = $plan->team;
        $isCarry = $plan->carry_root_code !== null;
        $editing = $plan->activity_id !== null;
        if ($editing) { $request->merge(['result' => $plan->status]); } // hasil tidak bisa diganti saat edit
        $allowed = $isCarry ? ['continue', 'closed'] : ['finish', 'pending'];

        $v = $request->validate([
            'result'              => 'required|in:' . implode(',', $allowed),
            'start_time'          => 'nullable|date_format:H:i',
            'finish_time'         => 'nullable|date_format:H:i',
            'done'                => 'nullable|string',
            'remaining'           => 'nullable|string',
            'next_plan'           => 'nullable|string',
            'pending_reason_type' => 'nullable|in:tidak_bisa,kendala',
            'pending_reason'      => 'nullable|string',
            'remark'              => 'nullable|string',
        ]);
        $result = $v['result'];
        $open = in_array($result, ['pending', 'continue']);
        $done = $this->lines($v['done'] ?? null); $remaining = $this->lines($v['remaining'] ?? null); $next = $this->lines($v['next_plan'] ?? null);

        $err = [];
        if ($open) {
            if (!$remaining) { $err['remaining'] = 'Isi minimal satu baris "problem yang belum selesai".'; }
            if (empty($v['pending_reason_type'])) { $err['pending_reason_type'] = 'Pilih alasan pending.'; }
            if (trim((string) ($v['pending_reason'] ?? '')) === '') { $err['pending_reason'] = 'Jelaskan kenapa problem ini pending.'; }
        } elseif (!$done) {
            $err['done'] = 'Isi minimal satu langkah "apa yang dikerjakan".';
        }
        if ($isCarry && !$editing) {
            $root = Activity::where('code', $plan->carry_root_code)->first();
            $chain = $root ? $root->chain() : collect();
            if ($chain->contains('status', 'closed')) { $err['result'] = 'Rantai ' . $plan->carry_root_code . ' sudah Closed oleh tim lain.'; }
            elseif ($chain->count() && $chain->last()->activity_date->toDateString() > $team->team_date->toDateString()) {
                $err['result'] = 'Tanggal tim lebih awal dari lanjutan terakhir rantai (' . $chain->last()->activity_date->format('d/m/Y') . ').';
            }
        }
        if ($err) { throw ValidationException::withMessages($err); }

        $shiftId = Shift::where('name', 'Shift ' . $team->shift)->value('id');
        $attrs = [
            'division_id' => $team->division_id, 'activity_date' => $team->team_date->toDateString(), 'shift_id' => $shiftId,
            'plan' => $team->plan_type, 'start_time' => $v['start_time'] ?? null, 'finish_time' => $v['finish_time'] ?? null,
            'device_id' => $plan->device_id, 'unit' => $plan->unit, 'location_id' => $plan->location_id, 'tool_id' => $plan->tool_id,
            'supervisor_id' => optional($this->master(Supervisor::class, optional($team->supervisor)->name))->id,
            'problem' => $plan->title, 'troubleshoot' => $done ? $this->numbered($done) : null,
            'remark' => $v['remark'] ?? null, 'team_id' => $team->id,
            'pending_reason_type' => $open ? $v['pending_reason_type'] : null, 'pending_reason' => $open ? trim($v['pending_reason']) : null,
        ];
        $techIds = [];
        foreach ($team->technicians as $m) { $techIds[] = optional($this->master(Technician::class, $m->name))->id; }
        $techIds = array_values(array_filter($techIds));

        DB::transaction(function () use ($plan, $team, $attrs, $techIds, $result, $isCarry, $editing, $done, $remaining, $next) {
            if ($editing) {
                $a = $plan->activity;
                $a->update($attrs);
                $action = 'followup_edit';
            } else {
                if ($isCarry) { $code = Activity::nextCode('PD'); $parent = $plan->carry_root_code; }
                elseif ($result === 'finish') { $code = Activity::nextCode($team->division->prefix); $parent = null; }
                else { $code = Activity::nextCode('PD'); $parent = null; }
                $a = Activity::create($attrs + ['code' => $code, 'parent_code' => $parent, 'status' => $result, 'created_by' => auth()->id()]);
                $action = 'followup';
            }
            $a->technicians()->sync($techIds);
            Troubleshoot::where('activity_id', $a->id)->delete();
            foreach (['done' => $done, 'remaining' => $remaining, 'next_plan' => $next] as $kind => $lines) {
                foreach ($lines as $i => $text) { Troubleshoot::create(['activity_id' => $a->id, 'kind' => $kind, 'seq' => $i + 1, 'text' => $text]); }
            }
            $plan->update(['status' => $a->status, 'activity_id' => $a->id]);
            ActivityLog::create(['activity_id' => $a->id, 'user_id' => auth()->id(), 'action' => $action, 'changes' => ['plan' => $plan->id, 'team' => $team->id]]);
        });

        $plan->refresh()->load('activity');
        $msg = 'Follow-up tersimpan: ' . $plan->activity->code . ' (' . ucfirst($plan->status) . ').';
        if ($plan->status === 'closed') { $msg .= ' Rantai ' . $plan->carry_root_code . ' ditutup.'; }
        return $this->toBoard($team)->with('success', $msg);
    }

    /** Batalkan hasil follow-up (plan kembali Open) bila belum ada lanjutan sesudahnya. */
    public function cancelResult(Plan $plan)
    {
        $a = $plan->activity;
        if (!$a) { return back()->with('error', 'Plan belum di-follow-up.'); }
        $chain = $a->chain();
        if ($chain->count() > 1 && $chain->last()->id !== $a->id) {
            return back()->with('error', 'Tidak bisa dibatalkan: sudah ada lanjutan setelah record ini di rantai ' . $a->root_code . '.');
        }
        DB::transaction(function () use ($plan, $a) {
            ActivityLog::create(['activity_id' => $a->id, 'user_id' => auth()->id(), 'action' => 'followup_cancel', 'changes' => ['plan' => $plan->id]]);
            Troubleshoot::where('activity_id', $a->id)->delete();
            $plan->update(['status' => 'open', 'activity_id' => null]);
            $a->delete(); // soft delete
        });
        return $this->toBoard($plan->team)->with('success', 'Hasil follow-up dibatalkan; plan kembali terbuka.');
    }

    // ------------------------------------------------------------------
    private function toBoard(Team $team)
    {
        return redirect()->route('act.dashboard', ['date' => $team->team_date->toDateString(), 'shift' => $team->shift]);
    }

    private function planData(Request $request): array
    {
        return MasterResolver::apply($request->validate([
            'title'         => 'required|string',
            'device_id'     => 'nullable|exists:act_devices,id',
            'device_name'   => 'nullable|string|max:255',
            'unit'          => 'nullable|string|max:50',
            'location_id'   => 'nullable|exists:act_locations,id',
            'location_name' => 'nullable|string|max:255',
            'tool_id'       => 'nullable|exists:act_tools,id',
            'note'          => 'nullable|string',
        ]));
    }

    /** Satu baris = satu langkah; nomor/bullet di depan dibuang. */
    private function lines(?string $s): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $s) as $l) {
            $l = trim(preg_replace('/^\s*(\d+[\.\)]|[-•*])\s*/u', '', str_replace(["\xC2\xA0", "\xE2\x81\xA0"], ' ', $l)));
            if ($l !== '') { $out[] = $l; }
        }
        return $out;
    }

    private function numbered(array $lines): string
    {
        $o = [];
        foreach ($lines as $i => $l) { $o[] = ($i + 1) . '. ' . $l; }
        return implode("\n", $o);
    }

    private function master(string $class, ?string $name)
    {
        $name = trim((string) $name);
        if ($name === '') { return null; }
        $m = $class::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])->first();
        if ($m) { return $m; }
        $attrs = ['name' => $name, 'is_active' => 1];
        if ($class === Supervisor::class) { $attrs['position'] = 'SPV'; }
        return $class::create($attrs);
    }
}
