@extends('layouts.home')
@section('content')
<div class="container-fluid">
  @include('act.partials.flash')
  <div class="row">
    <div class="col-md-7">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title">Detail <b>{{ $activity->code }}</b> @include('act.partials.status', ['s' => $activity->status])</h3>
          <div>
            <a href="{{ route('act.activity.edit', $activity->id) }}" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Edit</a>
            <a href="{{ route('act.activity.index', ['period' => 'daily', 'date' => $activity->activity_date->toDateString()]) }}" class="btn btn-sm btn-default">Kembali</a>
          </div>
        </div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <tr><th style="width:150px">ID Induk</th><td>{{ $activity->parent_code ?: '-' }}</td></tr>
            <tr><th>Divisi</th><td>{{ optional($activity->division)->name }}</td></tr>
            <tr><th>Tanggal</th><td>{{ $activity->activity_date->format('d/m/Y') }} {{ optional($activity->shift)->name }}</td></tr>
            <tr><th>Plan</th><td>{{ ucfirst($activity->plan) }}</td></tr>
            <tr><th>Waktu</th><td>{{ substr((string) $activity->start_time, 0, 5) }} – {{ substr((string) $activity->finish_time, 0, 5) }}</td></tr>
            <tr><th>Device / Unit</th><td>{{ optional($activity->device)->name }} {{ $activity->unit }}</td></tr>
            <tr><th>Lokasi</th><td>{{ optional($activity->location)->name }}</td></tr>
            <tr><th>Pengawas</th><td>{{ optional($activity->supervisor)->name }}</td></tr>
            <tr><th>Team</th><td>{{ $activity->technicians->pluck('name')->implode(', ') }}</td></tr>
            <tr><th>Sarana</th><td>{{ optional($activity->tool)->name }}</td></tr>
            <tr><th>Problem</th><td>{{ $activity->problem }}</td></tr>
            <tr><th>Troubleshoot</th><td style="white-space:pre-line">{{ $activity->troubleshoot }}</td></tr>
            <tr><th>Remark</th><td>{{ $activity->remark }}</td></tr>
          </table>
        </div>
      </div>
    </div>

    <div class="col-md-5">
      @if ($chain->count() > 1)
        <div class="card">
          <div class="card-header"><h3 class="card-title">Rantai Pending ({{ $chain->count() }} baris)</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              @foreach ($chain as $c)
                <tr class="{{ $c->id === $activity->id ? 'table-active' : '' }}">
                  <td><a href="{{ route('act.activity.show', $c->id) }}">{{ $c->code }}</a></td>
                  <td>{{ $c->activity_date->format('d/m/Y') }}</td>
                  <td>@include('act.partials.status', ['s' => $c->status])</td>
                </tr>
              @endforeach
            </table>
          </div>
        </div>
      @endif

      <div class="card">
        <div class="card-header"><h3 class="card-title">Riwayat Perubahan</h3></div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            @forelse ($activity->logs->sortByDesc('id') as $log)
              <tr>
                <td>{{ $log->created_at ? $log->created_at->format('d/m/Y H:i') : '' }}</td>
                <td>{{ ucfirst($log->action) }}</td>
                <td>{{ optional($log->user)->name ?: '-' }}</td>
              </tr>
            @empty
              <tr><td class="text-muted">Belum ada riwayat.</td></tr>
            @endforelse
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
