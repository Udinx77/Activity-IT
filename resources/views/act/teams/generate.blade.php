@extends('layouts.home')
@section('content')
<div class="container-fluid">
  @include('act.partials.flash')

  <div class="card">
    <div class="card-body">
      <form method="GET" action="{{ route('act.team.generate') }}" class="form-inline">
        <label class="mr-2">Tanggal</label>
        <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm mr-3">
        <label class="mr-2">Shift</label>
        <select name="shift" class="form-control form-control-sm mr-3">
          <option value="1" {{ $shift == 1 ? 'selected' : '' }}>Shift 1 (07:00–17:00) — roster H</option>
          <option value="2" {{ $shift == 2 ? 'selected' : '' }}>Shift 2 (17:01–06:59) — roster M</option>
        </select>
        <button class="btn btn-sm btn-primary mr-3"><i class="fas fa-search"></i> Tampilkan</button>
        <a href="{{ route('act.dashboard', ['date' => $date, 'shift' => $shift]) }}" class="btn btn-sm btn-outline-secondary">Ke Dashboard Activity</a>
      </form>
      <div class="mt-3">
        <b>Manpower bekerja:</b> {{ $pool->count() }} orang
        (Supervisor SCH/UNS: {{ $pool->whereNotNull('sup_type')->count() }}, teknisi: {{ $pool->whereNull('sup_type')->count() }}).
        <span class="text-muted ml-2">Kode roster tanggal ini:
          @forelse ($codes as $code => $n) <span class="badge badge-light">{{ $code ?: '(kosong)' }} {{ $n }}</span> @empty <i>tidak ada data roster</i> @endforelse
        </span>
      </div>
      @if ($pool->count() === 0)
        <div class="alert alert-warning mt-3 mb-0">Tidak ada manpower yang terjadwal <b>{{ $shift == 1 ? 'H' : 'M' }}</b> pada tanggal ini. Pastikan roster bulan ini sudah diimport di menu Attendance.</div>
      @elseif ($pool->whereNotNull('sup_type')->count() === 0)
        <div class="alert alert-info mt-3 mb-0">
          Tidak ada karyawan berjabatan <b>Supervisor SCH / UNS</b> di roster ini, jadi tim dibuat <b>tanpa pengawas</b> (pilih tipe plan manual). Jabatan yang ada:
          {{ $pool->pluck('position')->unique()->filter()->implode(', ') }}.
          (Aturan pengawas ada di <code>RosterService::supervisorType()</code>.)
        </div>
      @endif
    </div>
  </div>

  <div class="row">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Manpower {{ $shift == 1 ? 'Shift 1' : 'Shift 2' }}</h3></div>
        <div class="card-body table-responsive p-0" style="max-height:620px;overflow:auto">
          <table class="table table-sm table-striped mb-0">
            <thead><tr><th>Nama</th><th>Jabatan</th><th>Status</th></tr></thead>
            <tbody>
            @foreach ($pool as $p)
              <tr>
                <td>{{ $p->name }}</td>
                <td class="text-muted">{{ $p->position }}</td>
                <td>
                  @if (isset($assigned[$p->nik])) <span class="badge badge-secondary">{{ $assigned[$p->nik] }}</span>
                  @elseif ($p->sup_type) <span class="badge badge-{{ $p->sup_type === 'schedule' ? 'success' : 'info' }}">Pengawas {{ strtoupper(substr($p->sup_type, 0, 3)) }}</span>
                  @else <span class="badge badge-light">Teknisi · belum ada tim</span> @endif
                </td>
              </tr>
            @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      @foreach ($divisions as $div)
        @php $list = $teams->get($div->id, collect()); @endphp
        <div class="card card-outline card-primary">
          <div class="card-header"><h3 class="card-title"><b>{{ $div->name }}</b> <span class="text-muted">· {{ $list->count() }} tim</span></h3></div>
          <div class="card-body">
            @foreach ($list as $t)
              <div class="d-flex justify-content-between align-items-start border rounded p-2 mb-2">
                <div>
                  <b>{{ $t->name }}</b> <span class="badge badge-{{ $t->plan_type === 'schedule' ? 'success' : 'info' }}">{{ ucfirst($t->plan_type) }}</span>
                  @if ($locked[$t->id] ?? false) <i class="fas fa-lock text-muted" title="Anggota dikunci (sudah ada troubleshoot)"></i> @endif
                  <div class="text-muted">Pengawas: {{ optional($t->supervisor)->name ?: 'tanpa pengawas' }} · Teknisi ({{ $t->technicians->count() }}): {{ $t->technicians->pluck('name')->implode(', ') }} · {{ $t->plans->count() }} plan</div>
                </div>
                <div class="text-nowrap">
                  @unless ($locked[$t->id] ?? false)
                    <a href="{{ route('act.team.edit', $t->id) }}" class="btn btn-xs btn-warning"><i class="fas fa-edit"></i> Ubah</a>
                    <form action="{{ route('act.team.destroy', $t->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus {{ $t->name }}?')">@csrf @method('DELETE')<button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button></form>
                  @endunless
                </div>
              </div>
            @endforeach

            <form method="POST" action="{{ route('act.team.store') }}" class="border-top pt-2">
              @csrf
              <input type="hidden" name="date" value="{{ $date }}"><input type="hidden" name="shift" value="{{ $shift }}"><input type="hidden" name="division_id" value="{{ $div->id }}">
              <div class="form-row">
                <div class="col-md-5 mb-2">
                  <select name="supervisor_nik" class="form-control form-control-sm">
                    <option value="">— Tanpa pengawas —</option>
                    @foreach ($freeSup as $p)<option value="{{ $p->nik }}">Pengawas: {{ $p->name }} — {{ $p->position }}</option>@endforeach
                  </select>
                </div>
                <div class="col-md-4 mb-2">
                  <select name="plan_type" class="form-control form-control-sm" title="Kosong = ikut jabatan pengawas. Wajib dipilih bila tanpa pengawas.">
                    <option value="">Tipe plan: ikut pengawas</option>
                    <option value="schedule">Schedule</option><option value="unschedule">Unschedule</option>
                  </select>
                </div>
                <div class="col-md-3 mb-2"><button class="btn btn-sm btn-primary btn-block"><i class="fas fa-plus"></i> Buat Tim</button></div>
              </div>
              <div class="form-row tech-row">
                <div class="col-md-3 mb-2">
                  <select name="technician_niks[]" class="form-control form-control-sm" required>
                    <option value="">Teknisi 1</option>
                    @foreach ($freeTech as $p)<option value="{{ $p->nik }}">{{ $p->name }} — {{ $p->position }}</option>@endforeach
                  </select>
                </div>
                <div class="col-md-3 mb-2">
                  <select name="technician_niks[]" class="form-control form-control-sm">
                    <option value="">Teknisi 2 (opsional)</option>
                    @foreach ($freeTech as $p)<option value="{{ $p->nik }}">{{ $p->name }} — {{ $p->position }}</option>@endforeach
                  </select>
                </div>
                <div class="col-auto mb-2 add-wrap"><button type="button" class="btn btn-sm btn-outline-secondary btn-add-tech"><i class="fas fa-plus"></i> Teknisi</button></div>
              </div>
            </form>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</div>
@endsection

@push('addon-script')
<script>
$(document).on('click', '.btn-add-tech', function () {
  var wrap = $(this).closest('.add-wrap');
  var cols = wrap.closest('.tech-row').find('select[name="technician_niks[]"]').closest('div');
  var col = cols.last().clone();
  col.find('select').val('').prop('required', false).find('option:first').text('Teknisi ' + (cols.length + 1) + ' (opsional)');
  col.insertBefore(wrap);
});
</script>
@endpush
