@extends('layouts.home')
@section('content')
<div class="container-fluid">
  @include('act.partials.flash')
  <div class="card" style="max-width:820px">
    <div class="card-header"><h3 class="card-title">{{ $isEdit ? 'Ubah Plan' : 'Tambah Plan' }} — <b>{{ $team->name }}</b> ({{ ucfirst($team->plan_type) }}) · {{ $team->team_date->format('d/m/Y') }} Shift {{ $team->shift }}</h3></div>
    <form method="POST" action="{{ $isEdit ? route('act.plan.update', $plan->id) : route('act.plan.store', $team->id) }}">
      @csrf @if ($isEdit) @method('PUT') @endif
      <div class="card-body">
        <div class="form-group">
          <label>Problem / Plan yang akan dikerjakan <span class="text-danger">*</span></label>
          <textarea name="title" rows="2" class="form-control" required>{{ old('title', $plan->title) }}</textarea>
        </div>
        <div class="row">
          <div class="col-md-4 form-group"><label>Device</label>
            <input type="text" name="device_name" class="form-control" list="dl-device" autocomplete="off" placeholder="pilih atau ketik device" value="{{ old('device_name', optional($plan->device)->name) }}"></div>
          <div class="col-md-4 form-group"><label>Unit</label><input type="text" name="unit" maxlength="50" class="form-control" list="dl-unit" autocomplete="off" value="{{ old('unit', $plan->unit) }}" placeholder="ketik / pilih, mis. KT8531"></div>
          <div class="col-md-4 form-group"><label>Lokasi</label>
            <input type="text" name="location_name" class="form-control" list="dl-location" autocomplete="off" placeholder="pilih atau ketik lokasi" value="{{ old('location_name', optional($plan->location)->name) }}"></div>
        </div>
        <div class="row">
          <div class="col-md-4 form-group"><label>Sarana</label>
            <select name="tool_id" class="form-control"><option value="">-</option>
              @foreach ($tools as $t)<option value="{{ $t->id }}" {{ (string) old('tool_id', $plan->tool_id) === (string) $t->id ? 'selected' : '' }}>{{ $t->name }}</option>@endforeach
            </select></div>
          <div class="col-md-8 form-group"><label>Catatan rencana (opsional)</label><input type="text" name="note" class="form-control" value="{{ old('note', $plan->note) }}"></div>
        </div>
      </div>
      <datalist id="dl-device">@foreach ($devices as $d)<option value="{{ $d->name }}">@endforeach</datalist>
      <datalist id="dl-location">@foreach ($locations as $l)<option value="{{ $l->name }}">@endforeach</datalist>
      <datalist id="dl-unit">@foreach (($units ?? []) as $u)<option value="{{ $u }}">@endforeach</datalist>
      <div class="card-footer">
        <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
        <a href="{{ route('act.dashboard', ['date' => $team->team_date->toDateString(), 'shift' => $team->shift]) }}" class="btn btn-default">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
