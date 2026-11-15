@extends('layouts.home')
@section('content')
<div class="container-fluid">
  @include('act.partials.flash')
  <div class="row">
    <div class="col-md-5">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Job Induk <b>{{ $root->code }}</b></h3></div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <tr><th style="width:120px">Divisi</th><td>{{ optional($root->division)->name }}</td></tr>
            <tr><th>Device / Unit</th><td>{{ optional($root->device)->name }} {{ $root->unit }}</td></tr>
            <tr><th>Lokasi</th><td>{{ optional($root->location)->name }}</td></tr>
            <tr><th>Pengawas</th><td>{{ optional($root->supervisor)->name }}</td></tr>
            <tr><th>Problem</th><td>{{ $root->problem }}</td></tr>
            <tr><th>Mulai</th><td>{{ $root->activity_date->format('d/m/Y') }}</td></tr>
          </table>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><h3 class="card-title">Riwayat ({{ $chain->count() }} baris)</h3></div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            @foreach ($chain as $c)
              <tr>
                <td>{{ $c->code }}</td><td>{{ $c->activity_date->format('d/m/Y') }}</td>
                <td>@include('act.partials.status', ['s' => $c->status])</td>
              </tr>
            @endforeach
          </table>
        </div>
      </div>
    </div>

    <div class="col-md-7">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Lanjutan Pengerjaan (ID baru, Induk: {{ $root->code }})</h3></div>
        <form method="POST" action="{{ route('act.pending.continue.store', $activity->id) }}">
          @csrf
          <div class="card-body">
            <div class="row">
              <div class="col-md-4 form-group">
                <label>Tanggal <span class="text-danger">*</span></label>
                <input type="date" name="activity_date" class="form-control" required
                       min="{{ $last->activity_date->format('Y-m-d') }}" value="{{ old('activity_date', \App\Services\Act\RosterService::today()->format('Y-m-d')) }}">
              </div>
              <div class="col-md-4 form-group">
                <label>Shift</label>
                <select name="shift_id" class="form-control">
                  <option value="">-</option>
                  @foreach ($shifts as $s)
                    <option value="{{ $s->id }}" {{ (string) old('shift_id') === (string) $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-2 form-group">
                <label>Start</label>
                <input type="time" name="start_time" class="form-control" value="{{ old('start_time') }}">
              </div>
              <div class="col-md-2 form-group">
                <label>Finish</label>
                <input type="time" name="finish_time" class="form-control" value="{{ old('finish_time') }}">
              </div>
            </div>

            <div class="form-group">
              <label>Troubleshoot (apa yang dikerjakan di shift ini) <span class="text-danger">*</span></label>
              <textarea name="troubleshoot" rows="6" class="form-control" required placeholder="1. ...&#10;2. ...">{{ old('troubleshoot') }}</textarea>
            </div>
            <div class="form-group">
              <label>Problem (isi hanya jika berubah)</label>
              <input type="text" name="problem" class="form-control" value="{{ old('problem') }}">
            </div>

            <div class="form-group">
              <label>Status setelah shift ini <span class="text-danger">*</span></label><br>
              <div class="custom-control custom-radio custom-control-inline">
                <input class="custom-control-input" type="radio" id="stc" name="status" value="continue" {{ old('status', 'continue') === 'continue' ? 'checked' : '' }}>
                <label class="custom-control-label" for="stc">Continue (belum selesai, lanjut lagi)</label>
              </div>
              <div class="custom-control custom-radio custom-control-inline">
                <input class="custom-control-input" type="radio" id="stx" name="status" value="closed" {{ old('status') === 'closed' ? 'checked' : '' }}>
                <label class="custom-control-label" for="stx">Closed (selesai)</label>
              </div>
            </div>

            <div class="form-group">
              <label>Team (default: sama seperti shift sebelumnya)</label>
              @include('act.partials.techpicker', ['technicians' => \App\Models\Act\Technician::where('is_active', 1)->orderBy('name')->get(), 'selectedTech' => $last->technicians->pluck('id')->all()])
            </div>
            <div class="form-group">
              <label>Remark</label>
              <input type="text" name="remark" class="form-control" value="{{ old('remark') }}" placeholder="mis. menunggu sparepart">
            </div>
          </div>
          <div class="card-footer">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Lanjutan</button>
            <a href="{{ route('act.pending.index') }}" class="btn btn-default">Batal</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
