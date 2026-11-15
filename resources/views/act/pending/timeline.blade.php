@extends('layouts.home')
@section('content')
<div class="container-fluid">
  @include('act.partials.flash')
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3 class="card-title">
        Timeline <b>{{ $root->code }}</b> — {{ optional($root->device)->name }} {{ $root->unit }}
        @if ($isClosed) <span class="badge badge-secondary">Closed</span> @else <span class="badge badge-danger">Aktif</span> @endif
        <span class="badge badge-light">{{ $age }} hari</span>
      </h3>
      <div>
        @unless ($isClosed)
          <a href="{{ route('act.pending.continue', $chain->last()->id) }}" class="btn btn-sm btn-primary"><i class="fas fa-forward"></i> Lanjutkan</a>
        @endunless
        <a href="{{ route('act.pending.index') }}" class="btn btn-sm btn-default">Kembali</a>
      </div>
    </div>
    <div class="card-body">
      <p>
        <b>Divisi:</b> {{ optional($root->division)->name }} &nbsp;|&nbsp;
        <b>Lokasi:</b> {{ optional($root->location)->name }} &nbsp;|&nbsp;
        <b>Pengawas:</b> {{ optional($root->supervisor)->name }}<br>
        <b>Problem:</b> {{ $root->problem }}
      </p>
      <div class="timeline">
        @foreach ($chain as $row)
          <div class="time-label"><span class="bg-{{ $row->status === 'closed' ? 'secondary' : 'red' }}">{{ $row->activity_date->format('d M Y') }}</span></div>
          <div>
            <i class="fas fa-{{ $row->status === 'closed' ? 'check' : ($row->status === 'continue' ? 'forward' : 'hourglass-half') }} bg-{{ $row->status === 'closed' ? 'success' : 'blue' }}"></i>
            <div class="timeline-item">
              <span class="time"><i class="fas fa-clock"></i> {{ substr((string) $row->start_time, 0, 5) ?: '-' }} – {{ substr((string) $row->finish_time, 0, 5) ?: '-' }} {{ optional($row->shift)->name }}</span>
              <h3 class="timeline-header">{{ $row->code }} @include('act.partials.status', ['s' => $row->status])
                <small class="text-muted">Team: {{ $row->technicians->pluck('name')->implode(', ') ?: '-' }}</small></h3>
              <div class="timeline-body" style="white-space:pre-line">{{ $row->troubleshoot ?: $row->problem }}@if ($row->remark)

<i>Remark: {{ $row->remark }}</i>@endif</div>
              @php $rem = $row->lines('remaining'); $nxt = $row->lines('next_plan'); @endphp
              @if ($row->pending_reason_type || $rem || $nxt)
                <div class="timeline-footer">
                  @if ($row->pending_reason_type)<div><b>Kenapa pending:</b> <span class="badge badge-warning">{{ $row->reason_label }}</span> {{ $row->pending_reason }}</div>@endif
                  @if ($rem)<div class="mt-1"><b>Belum selesai:</b><ul class="mb-1">@foreach ($rem as $l)<li>{{ $l }}</li>@endforeach</ul></div>@endif
                  @if ($nxt)<div class="mt-1"><b>Plan tambahan:</b><ul class="mb-0">@foreach ($nxt as $l)<li>{{ $l }}</li>@endforeach</ul></div>@endif
                </div>
              @endif
            </div>
          </div>
        @endforeach
        <div><i class="fas fa-clock bg-gray"></i></div>
      </div>
    </div>
  </div>
</div>
@endsection
