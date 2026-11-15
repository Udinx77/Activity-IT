@extends('layouts.home')
@section('content')
<div class="container-fluid">
  @include('act.partials.flash')
  @include('act.partials.board')

  <div class="row">
    <div class="col-lg-3 col-6">
      <div class="small-box bg-info">
        <div class="inner"><h3>{{ $cards['today'] }}</h3><p>Activity Hari Ini</p></div>
        <div class="icon"><i class="fas fa-clipboard-list"></i></div>
        <a href="{{ route('act.activity.index') }}" class="small-box-footer">Lihat Daily <i class="fas fa-arrow-circle-right"></i></a>
      </div>
    </div>
    <div class="col-lg-3 col-6">
      <div class="small-box bg-success">
        <div class="inner"><h3>{{ $cards['finish_today'] }}</h3><p>Finish Hari Ini</p></div>
        <div class="icon"><i class="fas fa-check"></i></div>
        <a href="{{ route('act.activity.create') }}" class="small-box-footer">Tambah Activity <i class="fas fa-arrow-circle-right"></i></a>
      </div>
    </div>
    <div class="col-lg-3 col-6">
      <div class="small-box bg-danger">
        <div class="inner"><h3>{{ $cards['pending'] }}</h3><p>Job Pending Aktif</p></div>
        <div class="icon"><i class="fas fa-hourglass-half"></i></div>
        <a href="{{ route('act.pending.index') }}" class="small-box-footer">Lihat Pending <i class="fas fa-arrow-circle-right"></i></a>
      </div>
    </div>
    <div class="col-lg-3 col-6">
      <div class="small-box bg-secondary">
        <div class="inner"><h3>{{ $cards['closed_month'] }}</h3><p>Selesai Bulan Ini</p></div>
        <div class="icon"><i class="fas fa-check-double"></i></div>
        <a href="{{ route('act.closed.index') }}" class="small-box-footer">Lihat Closed <i class="fas fa-arrow-circle-right"></i></a>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Activity 7 Hari Terakhir</h3></div>
        <div class="card-body"><canvas id="chart7" style="min-height:260px;height:260px;max-height:260px"></canvas></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Per Divisi (bulan ini)</h3></div>
        <div class="card-body">
          @if ($perDivision->count())
            <canvas id="chartDiv" style="min-height:260px;height:260px;max-height:260px"></canvas>
          @else
            <p class="text-muted mb-0">Belum ada data bulan ini.</p>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h3 class="card-title">Job Pending Aktif Terlama</h3></div>
    <div class="card-body table-responsive p-0">
      <table class="table table-hover mb-0">
        <thead><tr><th>ID Induk</th><th>Divisi</th><th>Device / Unit</th><th>Lokasi</th><th>Problem</th><th>Mulai</th><th>Umur</th><th></th></tr></thead>
        <tbody>
        @forelse ($topPending as $c)
          <tr>
            <td><b>{{ $c->root_code }}</b></td>
            <td>{{ optional($c->first->division)->name }}</td>
            <td>{{ optional($c->first->device)->name }} {{ $c->first->unit }}</td>
            <td>{{ optional($c->first->location)->name }}</td>
            <td>{{ \Illuminate\Support\Str::limit($c->first->problem, 60) }}</td>
            <td>{{ $c->first->activity_date->format('d/m/Y') }}</td>
            <td><span class="badge badge-{{ $c->age_days >= $threshold ? 'danger' : 'warning' }}">{{ $c->age_days }} hari</span></td>
            <td class="text-right">
              <a href="{{ route('act.pending.continue', $c->last->id) }}" class="btn btn-xs btn-primary">Lanjutkan</a>
              <a href="{{ route('act.pending.timeline', $c->last->id) }}" class="btn btn-xs btn-outline-secondary">Timeline</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted">Tidak ada job pending. 🎉</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@push('addon-script')
<script>
$(function () {
  new Chart(document.getElementById('chart7').getContext('2d'), {
    type: 'bar',
    data: {
      labels: @json($labels),
      datasets: [
        {label: 'Selesai (Finish/Closed)', backgroundColor: '#28a745', data: @json($done)},
        {label: 'Belum selesai (Pending/Continue)', backgroundColor: '#dc3545', data: @json($open)}
      ]
    },
    options: {responsive: true, maintainAspectRatio: false,
      scales: {yAxes: [{ticks: {beginAtZero: true, precision: 0}}]}}
  });
  @if ($perDivision->count())
  new Chart(document.getElementById('chartDiv').getContext('2d'), {
    type: 'doughnut',
    data: {
      labels: @json($perDivision->keys()),
      datasets: [{data: @json($perDivision->values()), backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6c757d']}]
    },
    options: {responsive: true, maintainAspectRatio: false}
  });
  @endif
});
</script>
@endpush
