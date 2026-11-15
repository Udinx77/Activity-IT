<?php

namespace App\Http\Controllers\Act;

use App\Http\Controllers\Controller;
use App\Models\Act\Division;
use App\Models\Act\Team;
use App\Models\Act\TeamMember;
use App\Services\Act\RosterService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Generate Tim manual dari manpower yang bekerja (roster H/M): Pengawas (opsional, Supervisor SCH/UNS) + minimal 1 Teknisi, tanpa batas atas. */
class TeamController extends Controller
{
    public function generate(Request $request)
    {
        [$date, $shift] = $this->context($request);
        $pool  = RosterService::pool($date, $shift);
        $teams = Team::with(['division', 'members', 'plans'])->where('team_date', $date)->where('shift', $shift)
            ->orderBy('division_id')->orderBy('id')->get();

        $assigned = [];
        foreach ($teams as $t) { foreach ($t->members as $m) { $assigned[$m->nik] = $t->name; } }

        return view('act.teams.generate', [
            'date' => $date, 'shift' => $shift, 'pool' => $pool, 'assigned' => $assigned,
            'freeSup'  => $pool->filter(function ($p) use ($assigned) { return $p->sup_type && !isset($assigned[$p->nik]); })->values(),
            'freeTech' => $pool->filter(function ($p) use ($assigned) { return !$p->sup_type && !isset($assigned[$p->nik]); })->values(),
            'teams' => $teams->groupBy('division_id'),
            'divisions' => Division::where('is_active', 1)->orderBy('id')->get(),
            'codes' => RosterService::codes($date),
            'locked' => $teams->mapWithKeys(function ($t) { return [$t->id => $t->isLocked()]; }),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date'             => 'required|date',
            'shift'            => 'required|in:1,2',
            'division_id'      => 'required|exists:act_divisions,id',
            'supervisor_nik'   => 'nullable|string',
            'plan_type'        => 'nullable|in:schedule,unschedule',
            'technician_niks'  => 'nullable|array|max:40',
            'technician_niks.*' => 'nullable|string',
        ]);
        $date  = Carbon::parse($data['date'])->toDateString();
        $shift = (int) $data['shift'];
        $techs = array_values(array_unique(array_filter($data['technician_niks'] ?? [])));

        [$sup, $techRows, $planType] = $this->compose($date, $shift, $data['supervisor_nik'] ?? null, $techs, null, $data['plan_type'] ?? null);
        $division = Division::findOrFail($data['division_id']);

        $team = DB::transaction(function () use ($date, $shift, $division, $sup, $techRows, $planType) {
            $n = Team::where('team_date', $date)->where('shift', $shift)->where('division_id', $division->id)->count() + 1;
            $team = Team::create([
                'team_date' => $date, 'shift' => $shift, 'division_id' => $division->id,
                'name' => 'Tim ' . $division->name . ' ' . $n, 'plan_type' => $planType, 'created_by' => auth()->id(),
            ]);
            $this->saveMembers($team, $sup, $techRows);
            return $team;
        });

        return redirect()->route('act.team.generate', ['date' => $date, 'shift' => $shift])
            ->with('success', $team->name . ' dibuat (' . ucfirst($planType) . '). Tim tampil di Dashboard Activity.');
    }

    public function edit(Team $team)
    {
        $team->load(['division', 'members']);
        $pool = RosterService::pool($team->team_date->toDateString(), (int) $team->shift);
        $takenElsewhere = TeamMember::where('team_date', $team->team_date->toDateString())->where('shift', $team->shift)
            ->where('team_id', '!=', $team->id)->pluck('nik')->all();
        $mine = $team->members->pluck('nik')->all();
        $avail = $pool->filter(function ($p) use ($takenElsewhere) { return !in_array($p->nik, $takenElsewhere); })->values();

        return view('act.teams.edit', [
            'team' => $team, 'locked' => $team->isLocked(),
            'sups' => $avail->filter(function ($p) { return (bool) $p->sup_type; })->values(),
            'techs' => $avail->filter(function ($p) { return !$p->sup_type; })->values(),
            'mine' => $mine, 'supNik' => optional($team->supervisor)->nik, 'planType' => $team->plan_type,
            'techNiks' => $team->technicians->pluck('nik')->all(),
        ]);
    }

    public function update(Request $request, Team $team)
    {
        if ($team->isLocked()) {
            return back()->with('error', 'Anggota tim dikunci karena sudah ada troubleshoot (follow-up).');
        }
        $data = $request->validate([
            'supervisor_nik' => 'nullable|string', 'plan_type' => 'nullable|in:schedule,unschedule',
            'technician_niks' => 'nullable|array|max:40', 'technician_niks.*' => 'nullable|string',
        ]);
        $techs = array_values(array_unique(array_filter($data['technician_niks'] ?? [])));
        [$sup, $techRows, $planType] = $this->compose($team->team_date->toDateString(), (int) $team->shift, $data['supervisor_nik'] ?? null, $techs, $team->id, $data['plan_type'] ?? null);

        DB::transaction(function () use ($team, $sup, $techRows, $planType) {
            TeamMember::where('team_id', $team->id)->delete();
            $this->saveMembers($team, $sup, $techRows);
            $team->update(['plan_type' => $planType]);
        });

        return redirect()->route('act.dashboard', ['date' => $team->team_date->toDateString(), 'shift' => $team->shift])
            ->with('success', $team->name . ' diperbarui.');
    }

    public function destroy(Team $team)
    {
        if ($team->isLocked()) {
            return back()->with('error', 'Tim tidak bisa dihapus karena sudah ada troubleshoot.');
        }
        $date = $team->team_date->toDateString(); $shift = $team->shift; $name = $team->name;
        $team->delete(); // anggota & plan ikut terhapus (cascade)
        return redirect()->route('act.dashboard', ['date' => $date, 'shift' => $shift])->with('success', $name . ' dihapus.');
    }

    // ------------------------------------------------------------------
    private function context(Request $request): array
    {
        [$d, $s] = RosterService::context();
        try { $date = $request->filled('date') ? Carbon::parse($request->get('date'))->toDateString() : $d; } catch (\Exception $e) { $date = $d; }
        $shift = in_array((int) $request->get('shift'), [1, 2]) ? (int) $request->get('shift') : $s;
        return [$date, $shift];
    }

    /**
     * Validasi komposisi tim terhadap roster & aturan; kembalikan [pengawas|null, [teknisi], plan_type].
     *  - pengawas opsional; bila diisi harus Supervisor SCH/UNS yang bekerja
     *  - teknisi minimal 1, tanpa batas atas
     *  - tipe plan: dipilih eksplisit, atau ikut jabatan pengawas; tim tanpa pengawas wajib memilih
     *  - satu orang hanya di satu tim per shift
     */
    private function compose(string $date, int $shift, ?string $supNik, array $techs, ?int $ignoreTeamId, ?string $planType = null): array
    {
        $pool = RosterService::pool($date, $shift)->keyBy('nik');
        $supNik = trim((string) $supNik) !== '' ? trim((string) $supNik) : null;
        $err = [];

        if (count($techs) < 1) { $err[] = 'Tim harus berisi minimal 1 teknisi.'; }
        if ($supNik && in_array($supNik, $techs, true)) { $err[] = 'Pengawas tidak boleh sekaligus menjadi teknisi.'; }

        $sup = null;
        if ($supNik) {
            $sup = $pool->get($supNik);
            if (!$sup) { $err[] = 'Pengawas tidak bekerja pada ' . $date . ' ' . RosterService::shiftLabel($shift) . ' (cek roster).'; }
            elseif (!$sup->sup_type) { $err[] = $sup->name . ' (' . $sup->position . ') bukan Supervisor SCH/UNS, tidak bisa menjadi pengawas.'; }
        }

        $techRows = [];
        foreach ($techs as $nik) {
            $t = $pool->get($nik);
            if (!$t) { $err[] = 'NIK ' . $nik . ' tidak bekerja pada ' . $date . ' ' . RosterService::shiftLabel($shift) . ' (cek roster).'; continue; }
            if ($t->sup_type) { $err[] = $t->name . ' berjabatan Supervisor, tidak bisa menjadi teknisi.'; continue; }
            $techRows[] = $t;
        }

        $type = $planType ?: ($sup ? $sup->sup_type : null);
        if (!$type) { $err[] = 'Tim tanpa pengawas harus memilih tipe plan (Schedule atau Unschedule).'; }

        $taken = TeamMember::with('team')->where('team_date', $date)->where('shift', $shift)
            ->whereIn('nik', array_values(array_filter(array_merge([$supNik], $techs))))
            ->when($ignoreTeamId, function ($q) use ($ignoreTeamId) { $q->where('team_id', '!=', $ignoreTeamId); })->get();
        foreach ($taken as $m) { $err[] = $m->name . ' sudah berada di ' . optional($m->team)->name . ' (satu orang hanya boleh di satu tim per shift).'; }

        if ($err) { throw ValidationException::withMessages(['members' => $err]); }
        return [$sup, $techRows, $type];
    }

    private function saveMembers(Team $team, $sup, array $techRows): void // $sup boleh null (tim tanpa pengawas)
    {
        $base = ['team_id' => $team->id, 'team_date' => $team->team_date->toDateString(), 'shift' => $team->shift];
        if ($sup) {
            TeamMember::create($base + ['nik' => $sup->nik, 'name' => $sup->name, 'position' => $sup->position, 'role' => 'pengawas', 'slot' => 0]);
        }
        foreach ($techRows as $i => $t) {
            TeamMember::create($base + ['nik' => $t->nik, 'name' => $t->name, 'position' => $t->position, 'role' => 'teknisi', 'slot' => $i + 1]);
        }
    }
}
