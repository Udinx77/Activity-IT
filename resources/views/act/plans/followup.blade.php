@extends('layouts.home')
@section('content')
<div class="container-fluid">
  @include('act.partials.flash')
  <div class="row">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Plan</h3></div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <tr><th style="width:110px">Tim</th><td>{{ $team->name }} <span class="badge badge-{{ $team->plan_type === 'schedule' ? 'success' : 'info' }}">{{ ucfirst($team->plan_type) }}</span></td></tr>
            <tr><th>Tanggal</th><td>{{ $team->team_date->format('d/m/Y') }} · Shift {{ $team->shift }}</td></tr>
            <tr><th>Pengawas</th><td>{{ optional($team->supervisor)->name ?: '— (tanpa pengawas)' }}</td></tr>
            <tr><th>Teknisi</th><td>{{ $team->technicians->pluck('name')->implode(', ') }}</td></tr>
            <tr><th>Problem</th><td>{{ $plan->title }}</td></tr>
            <tr><th>Device / Unit</th><td>{{ optional($plan->device)->name }} {{ $plan->unit }}</td></tr>
            <tr><th>Lokasi</th><td>{{ optional($plan->location)->name }}</td></tr>
            <tr><th>Sarana</th><td>{{ optional($plan->tool)->name }}</td></tr>
            @if ($plan->note)<tr><th>Rencana</th><td style="white-space:pre-line">{{ $plan->note }}</td></tr>@endif
            @if ($isCarry)<tr><th>ID Induk</th><td><b>{{ $plan->carry_root_code }}</b></td></tr>@endif
            @if ($act)<tr><th>ID</th><td><b>{{ $act->code }}</b> @include('act.partials.status', ['s' => $act->status])</td></tr>@endif
          </table>
        </div>
      </div>
      @if ($chain->count())
        <div class="card">
          <div class="card-header"><h3 class="card-title">Riwayat rantai {{ $plan->carry_root_code }}</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              @foreach ($chain as $c)
                <tr><td>{{ $c->code }}</td><td>{{ $c->activity_date->format('d/m/Y') }}</td><td>@include('act.partials.status', ['s' => $c->status])</td>
                    <td class="text-muted">{{ $c->reason_label }}{{ $c->pending_reason ? ': ' . \Illuminate\Support\Str::limit($c->pending_reason, 50) : '' }}</td></tr>
              @endforeach
            </table>
          </div>
        </div>
      @endif
    </div>

    <div class="col-lg-8">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Form Troubleshoot {{ $editing ? '(ubah)' : '' }}</h3></div>
        <form method="POST" action="{{ route('act.plan.followup.save', $plan->id) }}">
          @csrf
          <div class="card-body">
            @php $lock = $editing ? 'disabled' : ''; $cur = old('result', $editing ? $plan->status : ($isCarry ? 'continue' : 'finish')); @endphp
            <div class="form-group">
              <label>Hasil <span class="text-danger">*</span></label><br>
              @foreach ($isCarry ? ['continue' => 'Masih pending (Continue)', 'closed' => 'Selesai (Closed)'] : ['finish' => 'Finish (selesai di shift ini)', 'pending' => 'Pending (belum selesai)'] as $val => $label)
                <div class="custom-control custom-radio custom-control-inline">
                  <input class="custom-control-input" type="radio" id="r_{{ $val }}" name="result" value="{{ $val }}" {{ $cur === $val ? 'checked' : '' }} {{ $lock }}>
                  <label class="custom-control-label" for="r_{{ $val }}">{{ $label }}</label>
                </div>
              @endforeach
              @if ($editing)<small class="text-muted d-block">Hasil tidak bisa diganti. Gunakan "Batalkan hasil" di Dashboard bila perlu.</small>@endif
            </div>

            <div class="row">
              <div class="col-md-3 form-group"><label>Jam mulai</label><input type="time" name="start_time" class="form-control" value="{{ old('start_time', $act ? substr((string) $act->start_time, 0, 5) : '') }}"></div>
              <div class="col-md-3 form-group"><label>Jam selesai</label><input type="time" name="finish_time" class="form-control" value="{{ old('finish_time', $act ? substr((string) $act->finish_time, 0, 5) : '') }}"></div>
            </div>

            <div class="form-group">
              <label><span id="lbl-done">Apa yang dikerjakan</span> <small class="text-muted">(satu baris = satu langkah)</small></label>
              <textarea name="done" rows="5" class="form-control" placeholder="1. Replace radio&#10;2. Test modulasi OK">{{ old('done', $act ? implode("\n", $act->lines('done')) : '') }}</textarea>
            </div>

            <div id="sec-open">
              <div class="form-group">
                <label>Problem apa yang belum selesai? <small class="text-muted">(satu baris = satu hal)</small> <span class="text-danger">*</span></label>
                <textarea name="remaining" rows="3" class="form-control">{{ old('remaining', $act ? implode("\n", $act->lines('remaining')) : '') }}</textarea>
              </div>
              <div class="form-group">
                <label>Kenapa problem ini pending? <span class="text-danger">*</span></label><br>
                @foreach (\App\Models\Act\Activity::REASONS as $key => $label)
                  <div class="custom-control custom-radio custom-control-inline">
                    <input class="custom-control-input" type="radio" id="why_{{ $key }}" name="pending_reason_type" value="{{ $key }}" {{ old('pending_reason_type', $act->pending_reason_type ?? '') === $key ? 'checked' : '' }}>
                    <label class="custom-control-label" for="why_{{ $key }}">{{ $label }}</label>
                  </div>
                @endforeach
                <textarea name="pending_reason" rows="2" class="form-control mt-2" placeholder="Jelaskan: mis. menunggu sparepart modem / tidak ada akses ke unit / kabel putus saat dicek">{{ old('pending_reason', $act->pending_reason ?? '') }}</textarea>
              </div>
              <div class="form-group">
                <label>Plan tambahan <small class="text-muted">(opsional, satu baris = satu rencana lanjutan; muncul sebagai usulan di tim berikutnya)</small></label>
                <textarea name="next_plan" rows="2" class="form-control">{{ old('next_plan', $act ? implode("\n", $act->lines('next_plan')) : '') }}</textarea>
              </div>
            </div>

            <div class="form-group"><label>Remark</label><input type="text" name="remark" class="form-control" value="{{ old('remark', $act->remark ?? '') }}"></div>
          </div>
          <div class="card-footer">
            <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan Troubleshoot</button>
            <a href="{{ route('act.dashboard', ['date' => $team->team_date->toDateString(), 'shift' => $team->shift]) }}" class="btn btn-default">Batal</a>
          </div>
        </form>
        @if ($editing)
          <div class="card-footer bg-white">
            <form method="POST" action="{{ route('act.plan.result.cancel', $plan->id) }}" onsubmit="return confirm('Batalkan hasil follow-up? Plan kembali terbuka dan {{ $act->code }} dihapus.')">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-outline-danger"><i class="fas fa-undo"></i> Batalkan hasil (buka kembali plan)</button>
            </form>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

@push('addon-script')
<script>
$(function () {
  function sync() {
    var r = $('input[name=result]:checked').val();
    var open = (r === 'pending' || r === 'continue');
    $('#sec-open').toggle(open);
    $('#lbl-done').text(open ? 'Yang sudah dikerjakan (opsional)' : 'Apa yang dikerjakan');
  }
  $('input[name=result]').on('change', sync); sync();
});
</script>
@endpush
