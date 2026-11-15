@extends('layouts.home')
@section('content')
<div class="container-fluid">
  @include('act.partials.flash')
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3 class="card-title">Job Pending / Continue (belum Closed)</h3>
      <a href="{{ route('act.pending.create') }}" class="btn btn-sm btn-danger"><i class="fas fa-plus"></i> Catat Job Pending Baru</a>
    </div>
    <div class="card-body">
      <p class="text-muted">
        Job yang tidak selesai dalam 1 shift. Tiap lanjutan dicatat sebagai baris baru (ID baru) yang tersambung ke
        <b>ID Induk</b>. Rantai tertutup saat statusnya <b>Closed</b>, lalu otomatis masuk daftar Closed.
        Umur ≥ {{ $threshold }} hari ditandai merah.
      </p>
      <table id="tblPending" class="table table-bordered table-striped" style="width:100%">
        <thead>
          <tr>
            <th>ID Induk</th><th>Divisi</th><th>Device</th><th>Unit</th><th>Lokasi</th><th>Pengawas</th><th>Problem</th>
            <th>Mulai</th><th>Baris</th><th>Status Terakhir</th><th>Alasan Pending</th><th>Umur</th><th class="no-export">Aksi</th>
          </tr>
        </thead>
        <tbody>
        @foreach ($chains as $c)
          <tr>
            <td><b>{{ $c->root_code }}</b></td>
            <td>{{ optional($c->first->division)->name }}</td>
            <td>{{ optional($c->first->device)->name }}</td>
            <td>{{ $c->first->unit }}</td>
            <td>{{ optional($c->first->location)->name }}</td>
            <td>{{ optional($c->first->supervisor)->name }}</td>
            <td style="min-width:200px">{{ $c->first->problem }}</td>
            <td data-order="{{ $c->first->activity_date->format('Y-m-d') }}">{{ $c->first->activity_date->format('d/m/Y') }}</td>
            <td>{{ $c->count }}</td>
            <td>@include('act.partials.status', ['s' => $c->last->status])</td>
            <td style="min-width:170px">{{ $c->last->reason_label }}{{ $c->last->pending_reason ? ': ' . \Illuminate\Support\Str::limit($c->last->pending_reason, 70) : '' }}</td>
            <td data-order="{{ $c->age_days }}"><span class="badge badge-{{ $c->age_days >= $threshold ? 'danger' : 'warning' }}">{{ $c->age_days }} hari</span></td>
            <td class="text-nowrap">
              <a href="{{ route('act.pending.continue', $c->last->id) }}" class="btn btn-xs btn-primary"><i class="fas fa-forward"></i> Lanjutkan</a>
              <a href="{{ route('act.pending.timeline', $c->last->id) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-stream"></i> Timeline</a>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@push('addon-script')
@include('act.partials.datatable', ['table' => 'tblPending', 'title' => 'Job Pending Aktif ' . \App\Services\Act\RosterService::now()->format('d-m-Y')])
@endpush
