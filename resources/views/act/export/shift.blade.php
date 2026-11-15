<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Laporan Aktivitas Tim IT — {{ $label }} {{ $shiftLabel }}</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #111; margin: 0; padding: 16px; }
  h1 { font-size: 17px; margin: 0 0 2px; } .sub { color: #444; margin-bottom: 10px; }
  .bar { background: #f3f4f6; border: 1px solid #d1d5db; padding: 8px 10px; border-radius: 6px; margin-bottom: 12px; }
  .bar button { padding: 6px 10px; margin-right: 6px; cursor: pointer; }
  h2 { font-size: 14px; background: #1f2937; color: #fff; padding: 4px 8px; margin: 14px 0 6px; }
  .team { border: 1px solid #9ca3af; margin-bottom: 10px; page-break-inside: avoid; }
  .team-h { background: #e5e7eb; padding: 5px 8px; }
  .badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 10px; color: #fff; }
  .sch { background: #198754; } .uns { background: #0d6efd; } .fin { background: #198754; } .pen { background: #dc3545; } .opn { background: #6b7280; } .cls { background: #495057; }
  table { width: 100%; border-collapse: collapse; } th, td { border-top: 1px solid #d1d5db; padding: 4px 6px; vertical-align: top; text-align: left; }
  th { background: #f9fafb; font-size: 11px; }
  ul { margin: 2px 0 2px 16px; padding: 0; } .muted { color: #555; } .why { color: #b02a37; }
  textarea { width: 100%; height: 160px; font-family: monospace; font-size: 12px; }
  .sum { margin-top: 12px; font-weight: bold; }
  @media print { .no-print { display: none !important; } body { padding: 0; } @page { size: A4; margin: 12mm; } }
</style>
</head>
<body>
<div class="bar no-print">
  <button onclick="window.print()">🖨️ Cetak / Simpan sebagai PDF</button>
  <button onclick="var t=document.getElementById('wa');t.style.display='block';t.select();document.execCommand('copy');this.textContent='✔ Teks tersalin (tempel di WhatsApp)';">📋 Salin teks WhatsApp</button>
  <a href="{{ route('act.dashboard', ['date' => $date, 'shift' => $shift]) }}">← Kembali ke Dashboard</a>
  <form method="GET" action="{{ route('act.export.shift') }}" style="display:inline;margin-left:12px">
    <input type="date" name="date" value="{{ $date }}"> <select name="shift"><option value="1" {{ $shift == 1 ? 'selected' : '' }}>Shift 1</option><option value="2" {{ $shift == 2 ? 'selected' : '' }}>Shift 2</option></select>
    <button>Tampilkan</button>
  </form>
  <textarea id="wa" style="display:none;margin-top:8px" readonly>{{ $wa }}</textarea>
</div>

<h1>Laporan Aktivitas Tim IT</h1>
<div class="sub">{{ $label }} · {{ $shiftLabel }}</div>

@forelse ($teams as $divName => $list)
  <h2>{{ strtoupper($divName) }}</h2>
  @foreach ($list as $t)
    <div class="team">
      <div class="team-h">
        <b>{{ $t->name }}</b> <span class="badge {{ $t->plan_type === 'schedule' ? 'sch' : 'uns' }}">{{ ucfirst($t->plan_type) }}</span>
        &nbsp; Pengawas: <b>{{ optional($t->supervisor)->name ?: '-' }}</b> &nbsp; Teknisi: <b>{{ $t->technicians->pluck('name')->implode(', ') }}</b>
      </div>
      <table>
        <thead><tr><th style="width:22px">No</th><th style="width:58px">ID</th><th>Problem / Plan</th><th style="width:20%">Device · Lokasi · Sarana</th><th style="width:60px">Status</th><th style="width:36%">Hasil troubleshoot</th></tr></thead>
        <tbody>
        @forelse ($t->plans as $i => $p)
          @php $a = $p->activity; $cls = ['finish' => 'fin', 'closed' => 'cls', 'pending' => 'pen', 'continue' => 'pen', 'open' => 'opn'][$p->status]; @endphp
          <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ optional($a)->code }}@if ($p->carry_root_code)<br><span class="muted">induk {{ $p->carry_root_code }}</span>@endif</td>
            <td>{{ $p->title }}</td>
            <td>{{ optional($p->device)->name }} {{ $p->unit }}<br><span class="muted">{{ optional($p->location)->name }}{{ optional($p->tool)->name ? ' · ' . $p->tool->name : '' }}</span></td>
            <td><span class="badge {{ $cls }}">{{ $p->status === 'open' ? 'Belum' : ucfirst($p->status) }}</span></td>
            <td>
              @if (!$a) <span class="muted">Belum di-follow-up</span>
              @else
                @if (count($a->lines('done')))<ul>@foreach ($a->lines('done') as $l)<li>{{ $l }}</li>@endforeach</ul>@endif
                @if (in_array($p->status, ['pending', 'continue']))
                  @if (count($a->lines('remaining')))<div><b>Belum selesai:</b><ul>@foreach ($a->lines('remaining') as $l)<li>{{ $l }}</li>@endforeach</ul></div>@endif
                  <div class="why"><b>Alasan:</b> {{ $a->reason_label }} — {{ $a->pending_reason }}</div>
                  @if (count($a->lines('next_plan')))<div><b>Plan tambahan:</b><ul>@foreach ($a->lines('next_plan') as $l)<li>{{ $l }}</li>@endforeach</ul></div>@endif
                @endif
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="muted">Belum ada plan.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  @endforeach
@empty
  <p class="muted">Belum ada tim untuk shift ini.</p>
@endforelse

<div class="sum">Ringkasan: {{ $sum['teams'] }} tim · {{ $sum['plans'] }} plan · selesai {{ $sum['finish'] }} · pending {{ $sum['pending'] }} · belum follow-up {{ $sum['open'] }}</div>
</body>
</html>
