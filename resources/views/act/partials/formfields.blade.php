{{-- Field bersama untuk form Activity & Pending. butuh: $activity + lookups + $selectedTech --}}
<div class="row">
  <div class="col-md-3 form-group">
    <label>Divisi <span class="text-danger">*</span></label>
    <select name="division_id" class="form-control" required>
      <option value="">- pilih -</option>
      @foreach ($divisions as $d)
        <option value="{{ $d->id }}" {{ (string) old('division_id', $activity->division_id) === (string) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-md-3 form-group">
    <label>Tanggal <span class="text-danger">*</span></label>
    <input type="date" name="activity_date" class="form-control" required
           value="{{ old('activity_date', optional($activity->activity_date)->format('Y-m-d')) }}">
  </div>
  <div class="col-md-3 form-group">
    <label>Shift</label>
    <select name="shift_id" class="form-control">
      <option value="">-</option>
      @foreach ($shifts as $s)
        <option value="{{ $s->id }}" {{ (string) old('shift_id', $activity->shift_id) === (string) $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-md-3 form-group">
    <label>Plan <span class="text-danger">*</span></label>
    <select name="plan" class="form-control" required>
      <option value="schedule" {{ old('plan', $activity->plan) === 'schedule' ? 'selected' : '' }}>Schedule</option>
      <option value="unschedule" {{ old('plan', $activity->plan) === 'unschedule' ? 'selected' : '' }}>Unschedule</option>
    </select>
  </div>
</div>

<div class="row">
  <div class="col-md-3 form-group">
    <label>Start Time</label>
    <input type="time" name="start_time" class="form-control" value="{{ old('start_time', substr((string) $activity->start_time, 0, 5)) }}">
  </div>
  <div class="col-md-3 form-group">
    <label>Finish Time</label>
    <input type="time" name="finish_time" class="form-control" value="{{ old('finish_time', substr((string) $activity->finish_time, 0, 5)) }}">
  </div>
  <div class="col-md-3 form-group">
    <label>Device</label>
    <input type="text" name="device_name" class="form-control" list="dl-device" autocomplete="off" placeholder="pilih atau ketik device"
           value="{{ old('device_name', optional($activity->device)->name) }}">
  </div>
  <div class="col-md-3 form-group">
    <label>Unit</label>
    <input type="text" name="unit" class="form-control" maxlength="50" list="dl-unit" autocomplete="off" placeholder="ketik / pilih, mis. KT8531" value="{{ old('unit', $activity->unit) }}">
  </div>
</div>

<div class="row">
  <div class="col-md-4 form-group">
    <label>Lokasi</label>
    <input type="text" name="location_name" class="form-control" list="dl-location" autocomplete="off" placeholder="pilih atau ketik lokasi"
           value="{{ old('location_name', optional($activity->location)->name) }}">
  </div>
  <div class="col-md-4 form-group">
    <label>Pengawas</label>
    <select name="supervisor_id" class="form-control">
      <option value="">-</option>
      @foreach ($supervisors as $s)
        <option value="{{ $s->id }}" {{ (string) old('supervisor_id', $activity->supervisor_id) === (string) $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-md-4 form-group">
    <label>Sarana</label>
    <select name="tool_id" class="form-control">
      <option value="">-</option>
      @foreach ($tools as $t)
        <option value="{{ $t->id }}" {{ (string) old('tool_id', $activity->tool_id) === (string) $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
      @endforeach
    </select>
  </div>
</div>

<div class="form-group">
  <label>Team (Teknisi)</label>
  @include('act.partials.techpicker', ['technicians' => $technicians, 'selectedTech' => $selectedTech])
</div>

<div class="form-group">
  <label>Problem {!! !empty($problemRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
  <textarea name="problem" rows="2" class="form-control" {{ !empty($problemRequired) ? 'required' : '' }}>{{ old('problem', $activity->problem) }}</textarea>
</div>
<div class="form-group">
  <label>Troubleshoot</label>
  <textarea name="troubleshoot" rows="6" class="form-control" placeholder="Satu langkah per baris, mis.&#10;1. Replace radio&#10;2. Test modulasi OK">{{ old('troubleshoot', $activity->troubleshoot) }}</textarea>
</div>
<div class="form-group">
  <label>Remark</label>
  <input type="text" name="remark" class="form-control" value="{{ old('remark', $activity->remark) }}">
</div>

<datalist id="dl-device">@foreach ($devices as $d)<option value="{{ $d->name }}">@endforeach</datalist>
<datalist id="dl-location">@foreach ($locations as $l)<option value="{{ $l->name }}">@endforeach</datalist>
<datalist id="dl-unit">@foreach (($units ?? []) as $u)<option value="{{ $u }}">@endforeach</datalist>
