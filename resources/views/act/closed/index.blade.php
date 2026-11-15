@extends('layouts.home')
@section('content')
<div class="container-fluid">
  @include('act.partials.flash')
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Closed — job Finish (1 shift) + job Pending yang sudah Closed</h3>
    </div>
    <div class="card-body">
      <form method="GET" action="{{ route('act.closed.index') }}" class="form-inline mb-3">
        <label class="mr-2">Dari</label>
        <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm mr-3">
        <label class="mr-2">Sampai</label>
        <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm mr-3">
        <select name="division_id" class="form-control form-control-sm mr-3">
          <option value="">Semua divisi</option>
          @foreach ($divisions as $d)
            <option value="{{ $d->id }}" {{ (string) request('division_id') === (string) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
          @endforeach
        </select>
        <button class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> Tampilkan</button>
        <span class="ml-3 text-muted">{{ $rows->count() }} baris</span>
      </form>

      <table id="tblClosed" class="table table-bordered table-striped" style="width:100%">
        <thead>
          <tr>
            <th>ID</th><th>ID Induk</th><th>Tanggal</th><th>Divisi</th><th>Shift</th><th>Plan</th><th>Start</th><th>Finish</th>
            <th>Device</th><th>Unit</th><th>Lokasi</th><th>Pengawas</th><th>Team</th><th>Problem</th><th>Troubleshoot</th><th>Sarana</th><th>Status</th><th>Remark</th>
          </tr>
        </thead>
        <tbody>
        @foreach ($rows as $a)
          <tr>
            <td><a href="{{ route('act.activity.show', $a->id) }}"><b>{{ $a->code }}</b></a></td>
            <td>{{ $a->parent_code }}</td>
            <td data-order="{{ $a->activity_date->format('Y-m-d') }}">{{ $a->activity_date->format('d/m/Y') }}</td>
            <td>{{ optional($a->division)->name }}</td>
            <td>{{ optional($a->shift)->name }}</td>
            <td>{{ ucfirst($a->plan) }}</td>
            <td>{{ substr((string) $a->start_time, 0, 5) }}</td>
            <td>{{ substr((string) $a->finish_time, 0, 5) }}</td>
            <td>{{ optional($a->device)->name }}</td>
            <td>{{ $a->unit }}</td>
            <td>{{ optional($a->location)->name }}</td>
            <td>{{ optional($a->supervisor)->name }}</td>
            <td>{{ $a->technicians->pluck('name')->implode(', ') }}</td>
            <td style="min-width:160px">{{ $a->problem }}</td>
            <td><div style="white-space:pre-line;min-width:240px">{{ $a->troubleshoot }}</div></td>
            <td>{{ optional($a->tool)->name }}</td>
            <td>@include('act.partials.status', ['s' => $a->status])</td>
            <td>{{ $a->remark }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@push('addon-script')
@include('act.partials.datatable', ['table' => 'tblClosed', 'title' => 'Closed Activity IT ' . $from . ' s.d. ' . $to])
@endpush
