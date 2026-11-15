@extends('layouts.home')
@section('content')
<div class="container-fluid">
  @include('act.partials.flash')
  <div class="card">
    <div class="card-header"><h3 class="card-title">Bawa Pending ke <b>{{ $team->name }}</b> · {{ $team->team_date->format('d/m/Y') }} Shift {{ $team->shift }}</h3></div>
    <form method="POST" action="{{ route('act.plan.carry.store', $team->id) }}">
      @csrf
      <div class="card-body table-responsive p-0">
        <table class="table table-sm table-hover mb-0">
          <thead><tr><th style="width:40px"></th><th>ID Induk</th><th>Divisi</th><th>Problem</th><th>Device / Unit</th><th>Lokasi</th><th>Umur</th><th>Alasan terakhir</th><th>Rencana lanjutan</th></tr></thead>
          <tbody>
          @forelse ($chains as $c)
            <tr>
              <td><input type="checkbox" name="root_codes[]" value="{{ $c->root_code }}"></td>
              <td><b>{{ $c->root_code }}</b></td>
              <td>{{ optional($c->first->division)->name }}</td>
              <td>{{ \Illuminate\Support\Str::limit($c->first->problem, 70) }}</td>
              <td>{{ optional($c->first->device)->name }} {{ $c->first->unit }}</td>
              <td>{{ optional($c->first->location)->name }}</td>
              <td><span class="badge badge-warning">{{ $c->age_days }} hari</span></td>
              <td>{{ $c->last->reason_label }}{{ $c->last->pending_reason ? ' — ' . \Illuminate\Support\Str::limit($c->last->pending_reason, 60) : '' }}</td>
              <td style="white-space:pre-line">{{ implode("\n", $c->last->lines('next_plan')) }}</td>
            </tr>
          @empty
            <tr><td colspan="9" class="text-center text-muted">Tidak ada pending aktif yang belum diplankan.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer">
        <button class="btn btn-primary" {{ $chains->count() ? '' : 'disabled' }}><i class="fas fa-plus"></i> Bawa ke Plan Tim</button>
        <a href="{{ route('act.dashboard', ['date' => $team->team_date->toDateString(), 'shift' => $team->shift]) }}" class="btn btn-default">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
