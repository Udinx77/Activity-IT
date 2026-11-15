@extends('layouts.home')
@section('content')
<div class="container-fluid">
  @include('act.partials.flash')
  <div class="card" style="max-width:760px">
    <div class="card-header"><h3 class="card-title">Ubah anggota <b>{{ $team->name }}</b> · {{ $team->team_date->format('d/m/Y') }} · Shift {{ $team->shift }}</h3></div>
    @if ($locked)
      <div class="card-body"><div class="alert alert-warning mb-0"><i class="fas fa-lock"></i> Anggota tim dikunci karena sudah ada troubleshoot (follow-up). Batalkan hasil follow-up bila anggota harus diubah.</div></div>
      <div class="card-footer"><a href="{{ route('act.dashboard', ['date' => $team->team_date->toDateString(), 'shift' => $team->shift]) }}" class="btn btn-default">Kembali</a></div>
    @else
    <form method="POST" action="{{ route('act.team.update', $team->id) }}">
      @csrf @method('PUT')
      <div class="card-body">
        <div class="form-group">
          <label>Pengawas <small class="text-muted">(opsional · Supervisor SCH/UNS)</small></label>
          <select name="supervisor_nik" class="form-control">
            <option value="">— Tanpa pengawas —</option>
            @foreach ($sups as $p)<option value="{{ $p->nik }}" {{ old('supervisor_nik', $supNik) === $p->nik ? 'selected' : '' }}>{{ $p->name }} — {{ $p->position }}</option>@endforeach
          </select>
        </div>
        <div class="form-group">
          <label>Tipe plan <small class="text-muted">(kosong = ikut jabatan pengawas; wajib dipilih bila tanpa pengawas)</small></label>
          <select name="plan_type" class="form-control" style="max-width:320px">
            <option value="">Ikut pengawas</option>
            <option value="schedule" {{ old('plan_type', $planType) === 'schedule' ? 'selected' : '' }}>Schedule</option>
            <option value="unschedule" {{ old('plan_type', $planType) === 'unschedule' ? 'selected' : '' }}>Unschedule</option>
          </select>
        </div>
        <div class="form-group tech-box">
          <label>Teknisi <small class="text-muted">(minimal 1, tanpa batas atas; kosongkan pilihan untuk mengeluarkan)</small></label>
          <div class="tech-list">
            @php $nT = max(2, count($techNiks) + 1); @endphp
            @for ($i = 0; $i < $nT; $i++)
              <select name="technician_niks[]" class="form-control mb-1" {{ $i === 0 ? 'required' : '' }}>
                <option value="">{{ $i === 0 ? 'Teknisi 1' : 'Teknisi ' . ($i + 1) . ' (opsional)' }}</option>
                @foreach ($techs as $p)<option value="{{ $p->nik }}" {{ (old('technician_niks.' . $i, $techNiks[$i] ?? null)) === $p->nik ? 'selected' : '' }}>{{ $p->name }} — {{ $p->position }}</option>@endforeach
              </select>
            @endfor
          </div>
          <button type="button" class="btn btn-sm btn-outline-secondary btn-add-tech"><i class="fas fa-plus"></i> Teknisi</button>
        </div>
        <p class="text-muted mb-0">Hanya manpower yang bekerja pada shift ini dan belum berada di tim lain yang ditampilkan.</p>
      </div>
      <div class="card-footer">
        <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
        <a href="{{ route('act.dashboard', ['date' => $team->team_date->toDateString(), 'shift' => $team->shift]) }}" class="btn btn-default">Batal</a>
      </div>
    </form>
    @endif
  </div>
</div>
@endsection

@push('addon-script')
<script>
$(document).on('click', '.btn-add-tech', function () {
  var list = $(this).closest('.tech-box').find('.tech-list');
  var sel = list.find('select').last().clone();
  sel.val('').prop('required', false).find('option:first').text('Teknisi ' + (list.find('select').length + 1) + ' (opsional)');
  list.append(sel);
});
</script>
@endpush
