@extends('layouts.home')
@section('content')
<div class="container-fluid">
  @include('act.partials.flash')

  <div class="card">
    <div class="card-header">
      <div class="d-flex flex-wrap justify-content-between align-items-center">
        <ul class="nav nav-pills">
          @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $k => $label)
            <li class="nav-item">
              <a class="nav-link {{ $period === $k ? 'active' : '' }}" href="{{ route('act.activity.index', ['period' => $k, 'date' => $date]) }}">{{ $label }}</a>
            </li>
          @endforeach
        </ul>
        <div class="d-flex align-items-center mt-2 mt-md-0">
          <a class="btn btn-sm btn-outline-secondary" href="{{ route('act.activity.index', ['period' => $period, 'date' => $prev]) }}"><i class="fas fa-chevron-left"></i></a>
          <form method="GET" action="{{ route('act.activity.index') }}" class="mx-2">
            <input type="hidden" name="period" value="{{ $period }}">
            <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm" onchange="this.form.submit()">
          </form>
          <a class="btn btn-sm btn-outline-secondary" href="{{ route('act.activity.index', ['period' => $period, 'date' => $next]) }}"><i class="fas fa-chevron-right"></i></a>
          <a class="btn btn-sm btn-primary ml-3" href="{{ route('act.activity.create') }}"><i class="fas fa-plus"></i> Tambah Activity</a>
        </div>
      </div>
      <div class="mt-2 text-muted">
        Periode: <b>{{ $start->format('d M Y') }}</b>@if ($start->ne($end->copy()->startOfDay())) s/d <b>{{ $end->format('d M Y') }}</b>@endif
        @if ($period === 'weekly') <span class="ml-1">(Senin–Minggu)</span> @endif
      </div>
    </div>

    <div class="card-body">
      <div class="row">
        <div class="col-md-3 col-6"><div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-list"></i></span><div class="info-box-content"><span class="info-box-text">Total</span><span class="info-box-number">{{ $metrics['total'] }}</span></div></div></div>
        <div class="col-md-3 col-6"><div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-check"></i></span><div class="info-box-content"><span class="info-box-text">Finish</span><span class="info-box-number">{{ $metrics['finish'] }}</span></div></div></div>
        <div class="col-md-3 col-6"><div class="info-box"><span class="info-box-icon bg-danger"><i class="fas fa-hourglass-half"></i></span><div class="info-box-content"><span class="info-box-text">Pending / Continue</span><span class="info-box-number">{{ $metrics['open'] }}</span></div></div></div>
        <div class="col-md-3 col-6"><div class="info-box"><span class="info-box-icon bg-secondary"><i class="fas fa-check-double"></i></span><div class="info-box-content"><span class="info-box-text">Closed</span><span class="info-box-number">{{ $metrics['closed'] }}</span></div></div></div>
      </div>

      <table id="tblAct" class="table table-bordered table-striped" style="width:100%">
        <thead>
          <tr>
            <th>ID</th><th>ID Induk</th><th>Tanggal</th><th>Divisi</th><th>Shift</th><th>Plan</th><th>Start</th><th>Finish</th>
            <th>Device</th><th>Unit</th><th>Lokasi</th><th>Pengawas</th><th>Team</th><th>Problem</th><th>Troubleshoot</th><th>Sarana</th><th>Status</th><th>Remark</th>
            <th class="no-export">Aksi</th>
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
            <td class="text-nowrap">
              <a href="{{ route('act.activity.show', $a->id) }}" class="btn btn-xs btn-info" title="Detail"><i class="fas fa-eye"></i></a>
              <a href="{{ route('act.activity.edit', $a->id) }}" class="btn btn-xs btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
              <form action="{{ route('act.activity.destroy', $a->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus {{ $a->code }}?')">
                @csrf @method('DELETE')
                <button class="btn btn-xs btn-danger" title="Hapus"><i class="fas fa-trash"></i></button>
              </form>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="row">
    @foreach ([['Per Divisi', $perDivision], ['Per Pengawas', $perSupervisor], ['Per Device', $perDevice], ['Top Problem', $topProblem]] as $box)
      <div class="col-md-3">
        <div class="card">
          <div class="card-header"><h3 class="card-title">{{ $box[0] }}</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              @forelse ($box[1] as $name => $total)
                <tr><td>{{ \Illuminate\Support\Str::limit($name, 38) }}</td><td class="text-right"><b>{{ $total }}</b></td></tr>
              @empty
                <tr><td class="text-muted">Tidak ada data</td></tr>
              @endforelse
            </table>
          </div>
        </div>
      </div>
    @endforeach
  </div>
</div>
@endsection

@push('addon-script')
@include('act.partials.datatable', ['table' => 'tblAct', 'title' => 'Activity IT ' . ucfirst($period) . ' ' . $start->format('d-m-Y') . ($start->ne($end->copy()->startOfDay()) ? ' s.d. ' . $end->format('d-m-Y') : '')])
@endpush
