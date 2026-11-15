@extends('layouts.home')
@section('content')
<div class="container-fluid">
  @include('act.partials.flash')
  @php $s = $res['summary']; @endphp

  <div class="card">
    <div class="card-header"><h3 class="card-title">Preview Import: <b>{{ $filename }}</b></h3></div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-3 col-6"><div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-file-alt"></i></span><div class="info-box-content"><span class="info-box-text">Baris terbaca</span><span class="info-box-number">{{ $s['read'] }}</span></div></div></div>
        <div class="col-md-3 col-6"><div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-plus"></i></span><div class="info-box-content"><span class="info-box-text">Akan ditambahkan</span><span class="info-box-number">{{ $s['new'] }}</span></div></div></div>
        <div class="col-md-3 col-6"><div class="info-box"><span class="info-box-icon bg-warning"><i class="fas fa-clone"></i></span><div class="info-box-content"><span class="info-box-text">Duplikat (dilewati)</span><span class="info-box-number">{{ $s['duplicate'] }}</span></div></div></div>
        <div class="col-md-3 col-6"><div class="info-box"><span class="info-box-icon bg-danger"><i class="fas fa-exclamation-triangle"></i></span><div class="info-box-content"><span class="info-box-text">Error (dilewati)</span><span class="info-box-number">{{ $s['error'] }}</span></div></div></div>
      </div>

      <p class="mb-1"><b>Sheet/sumber:</b> {{ implode(', ', $res['sheets']) ?: '-' }}</p>
      <p class="mb-1"><b>Kolom dikenali:</b> {{ implode(', ', $res['columns_used']) ?: '-' }}</p>
      @if (count($res['columns_ignored']))
        <p class="mb-1 text-muted"><b>Kolom diabaikan:</b> {{ implode(', ', $res['columns_ignored']) }}</p>
      @endif
      @php $labels = ['device' => 'Device', 'location' => 'Lokasi', 'tool' => 'Sarana', 'supervisor' => 'Pengawas', 'technician' => 'Teknisi']; @endphp
      @foreach ($res['new_masters'] as $type => $names)
        @if (count($names))
          <p class="mb-1"><b>{{ $labels[$type] }} baru ({{ count($names) }}):</b> <span class="text-muted">{{ implode(', ', array_slice($names, 0, 25)) }}{{ count($names) > 25 ? ', …' : '' }}</span></p>
        @endif
      @endforeach

      <form method="POST" action="{{ route('act.import.confirm') }}" class="mt-3">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <button class="btn btn-success" {{ $s['new'] == 0 ? 'disabled' : '' }}
                onclick="return confirm('Simpan {{ $s['new'] }} baris ke database?')"><i class="fas fa-check"></i> Konfirmasi Import ({{ $s['new'] }} baris)</button>
        <a href="{{ route('act.import.index') }}" class="btn btn-default">Batal / Upload Ulang</a>
        @if ($s['error'] > 0)<span class="text-danger ml-2">Baris error tidak ikut diimport. Perbaiki di file lalu upload ulang bila perlu.</span>@endif
      </form>
    </div>
  </div>

  @if (count($errorRows))
    <div class="card card-danger card-outline">
      <div class="card-header"><h3 class="card-title">Baris Error ({{ $s['error'] }}){{ $s['error'] > 300 ? ' — 300 pertama' : '' }}</h3></div>
      <div class="card-body table-responsive p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th style="width:90px">Baris</th><th style="width:110px">Sheet</th><th>Masalah</th></tr></thead>
          <tbody>
          @foreach ($errorRows as $r)
            <tr><td>{{ $r['line'] }}</td><td>{{ $r['sheet'] }}</td><td>{{ implode('; ', $r['errors']) }}</td></tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  @if (count($dupRows))
    <div class="card card-warning card-outline">
      <div class="card-header"><h3 class="card-title">Contoh Duplikat ({{ $s['duplicate'] }})</h3></div>
      <div class="card-body table-responsive p-0">
        <table class="table table-sm mb-0">
          <tbody>
          @foreach ($dupRows as $r)
            <tr><td style="width:90px">{{ $r['line'] }}</td><td style="width:110px">{{ $r['sheet'] }}</td><td>{{ implode('; ', $r['warnings']) }}</td></tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  @if (count($newRows))
    <div class="card">
      <div class="card-header"><h3 class="card-title">Data yang Akan Ditambahkan{{ $s['new'] > 100 ? ' (100 pertama dari ' . $s['new'] . ')' : '' }}</h3></div>
      <div class="card-body table-responsive p-0">
        <table class="table table-sm table-striped mb-0">
          <thead>
            <tr><th>Baris</th><th>ID</th><th>Induk</th><th>Tanggal</th><th>Divisi</th><th>Plan</th><th>Jam</th><th>Device</th><th>Unit</th><th>Lokasi</th><th>Pengawas</th><th>Team</th><th>Problem</th><th>Status</th><th>Catatan</th></tr>
          </thead>
          <tbody>
          @foreach ($newRows as $r)
            @php $d = $r['d']; @endphp
            <tr>
              <td>{{ $r['line'] }}</td>
              <td>{{ $d['code'] ?: 'otomatis' }}</td>
              <td>{{ $d['parent'] }}</td>
              <td>{{ \Carbon\Carbon::parse($d['date'])->format('d/m/Y') }}</td>
              <td>{{ $d['division'] }}</td>
              <td>{{ ucfirst($d['plan']) }}</td>
              <td>{{ substr((string) $d['start'], 0, 5) }}{{ $d['finish'] ? '–' . substr($d['finish'], 0, 5) : '' }}</td>
              <td>{{ $d['device'] }}</td>
              <td>{{ $d['unit'] }}</td>
              <td>{{ $d['location'] }}</td>
              <td>{{ $d['supervisor'] }}</td>
              <td>{{ implode(', ', $d['team']) }}</td>
              <td>{{ \Illuminate\Support\Str::limit($d['problem'], 50) }}</td>
              <td>@include('act.partials.status', ['s' => $d['status']])</td>
              <td class="text-muted">{{ implode('; ', $r['warnings']) }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif
</div>
@endsection
