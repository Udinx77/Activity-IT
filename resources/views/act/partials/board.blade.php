{{-- Papan tim: butuh $hasTeams, $boardDate, $boardShift, $boardTeams (grup per division_id), $boardDivisions --}}
@if (!$hasTeams)
  <div class="alert alert-warning">Tabel tim belum ada. Import <code>database/act_v2_teams.sql</code> lewat phpMyAdmin terlebih dahulu.</div>
@else
<div class="card card-primary card-outline">
  <div class="card-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
      <h3 class="card-title mb-2 mb-md-0">Tim &amp; Plan — {{ \Carbon\Carbon::parse($boardDate)->format('d/m/Y') }} · {{ $boardShift == 1 ? 'Shift 1 (07:00–17:00)' : 'Shift 2 (17:01–06:59)' }}</h3>
      <div class="d-flex flex-wrap align-items-center">
        <form method="GET" action="{{ route('act.dashboard') }}" class="form-inline mr-2">
          <input type="date" name="date" value="{{ $boardDate }}" class="form-control form-control-sm mr-1" onchange="this.form.submit()">
          <select name="shift" class="form-control form-control-sm" onchange="this.form.submit()">
            <option value="1" {{ $boardShift == 1 ? 'selected' : '' }}>Shift 1</option><option value="2" {{ $boardShift == 2 ? 'selected' : '' }}>Shift 2</option>
          </select>
        </form>
        <a href="{{ route('act.team.generate', ['date' => $boardDate, 'shift' => $boardShift]) }}" class="btn btn-sm btn-primary mr-1"><i class="fas fa-users"></i> Generate Tim</a>
        <a href="{{ route('act.export.shift', ['date' => $boardDate, 'shift' => $boardShift]) }}" target="_blank" class="btn btn-sm btn-success"><i class="fas fa-file-pdf"></i> Export PDF (semua tim)</a>
      </div>
    </div>
  </div>
  <div class="card-body">
    @if ($boardTeams->flatten()->count() === 0)
      <p class="text-muted mb-0">Belum ada tim untuk shift ini. Klik <b>Generate Tim</b> untuk membentuk tim dari manpower yang bekerja.</p>
    @endif
    @foreach ($boardDivisions as $div)
      @php $dTeams = $boardTeams->get($div->id, collect()); @endphp
      @if ($dTeams->count())
        <h5 class="mt-2"><span class="badge badge-dark">{{ $div->name }}</span></h5>
        <div class="row">
          @foreach ($dTeams as $t)
            @php
              $locked = $t->plans->contains(function ($p) { return $p->activity_id !== null; });
              $doneN = $t->plans->where('status', '!=', 'open')->count();
            @endphp
            <div class="col-xl-6">
              <div class="card card-outline card-{{ $t->plan_type === 'schedule' ? 'success' : 'info' }}">
                <div class="card-header">
                  <h3 class="card-title"><b>{{ $t->name }}</b> <span class="badge badge-{{ $t->plan_type === 'schedule' ? 'success' : 'info' }}">{{ ucfirst($t->plan_type) }}</span>
                    @if ($locked) <i class="fas fa-lock text-muted ml-1" title="Anggota dikunci"></i> @endif</h3>
                  <div class="card-tools">
                    <span class="badge badge-light mr-1">{{ $doneN }}/{{ $t->plans->count() }} di-follow-up</span>
                    @unless ($locked)
                      <a href="{{ route('act.team.edit', $t->id) }}" class="btn btn-tool" title="Ubah anggota"><i class="fas fa-edit"></i></a>
                      <form action="{{ route('act.team.destroy', $t->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus {{ $t->name }} beserta plannya?')">@csrf @method('DELETE')<button class="btn btn-tool" title="Hapus tim"><i class="fas fa-trash"></i></button></form>
                    @endunless
                  </div>
                </div>
                <div class="card-body pb-1">
                  <div class="mb-2"><i class="fas fa-user-shield"></i> @if ($t->supervisor)<b>{{ $t->supervisor->name }}</b>@else<span class="text-muted">tanpa pengawas</span>@endif
                    <span class="mx-1">·</span><i class="fas fa-user-cog"></i> {{ $t->technicians->pluck('name')->implode(', ') }}</div>
                  <table class="table table-sm mb-2">
                    @forelse ($t->plans as $p)
                      <tr>
                        <td style="width:34px" class="text-center">
                          @if ($p->status === 'open')
                            <a href="{{ route('act.plan.followup', $p->id) }}" title="Follow-up troubleshoot"><i class="far fa-square fa-lg text-secondary"></i></a>
                          @else
                            <a href="{{ route('act.plan.followup', $p->id) }}" title="Lihat / ubah follow-up"><i class="fas fa-check-square fa-lg text-{{ in_array($p->status, ['finish', 'closed']) ? 'success' : 'danger' }}"></i></a>
                          @endif
                        </td>
                        <td>
                          <div>{{ $p->title }} @if ($p->carry_root_code)<span class="badge badge-warning">lanjutan {{ $p->carry_root_code }}</span>@endif</div>
                          <small class="text-muted">{{ optional($p->device)->name }} {{ $p->unit }}{{ optional($p->location)->name ? ' · ' . $p->location->name : '' }}{{ optional($p->tool)->name ? ' · ' . $p->tool->name : '' }}</small>
                          @if ($p->note && $p->status === 'open')<div><small class="text-info" style="white-space:pre-line">Rencana: {{ $p->note }}</small></div>@endif
                        </td>
                        <td class="text-right text-nowrap">
                          @if ($p->activity) <b>{{ $p->activity->code }}</b> @include('act.partials.status', ['s' => $p->status])
                          @else
                            <a href="{{ route('act.plan.followup', $p->id) }}" class="btn btn-xs btn-primary">Follow-up</a>
                            <a href="{{ route('act.plan.edit', $p->id) }}" class="btn btn-xs btn-outline-warning"><i class="fas fa-edit"></i></a>
                            <form action="{{ route('act.plan.destroy', $p->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus plan ini?')">@csrf @method('DELETE')<button class="btn btn-xs btn-outline-danger"><i class="fas fa-times"></i></button></form>
                          @endif
                        </td>
                      </tr>
                    @empty
                      <tr><td class="text-muted">Belum ada plan.</td></tr>
                    @endforelse
                  </table>
                </div>
                <div class="card-footer py-2">
                  <a href="{{ route('act.plan.create', $t->id) }}" class="btn btn-xs btn-outline-primary"><i class="fas fa-plus"></i> Plan</a>
                  <a href="{{ route('act.plan.carry', $t->id) }}" class="btn btn-xs btn-outline-warning"><i class="fas fa-redo"></i> Bawa dari Pending</a>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      @endif
    @endforeach
  </div>
</div>
@endif
