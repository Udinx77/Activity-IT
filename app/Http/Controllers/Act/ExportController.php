<?php

namespace App\Http\Controllers\Act;

use App\Http\Controllers\Controller;
use App\Models\Act\Activity;
use App\Models\Act\Team;
use App\Services\Act\RosterService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Laporan semua tim dalam 1 shift: halaman cetak (Simpan sebagai PDF) + teks WhatsApp. */
class ExportController extends Controller
{
    public function shift(Request $request)
    {
        [$d, $s] = RosterService::context();
        try { $date = $request->filled('date') ? Carbon::parse($request->get('date'))->toDateString() : $d; } catch (\Exception $e) { $date = $d; }
        $shift = in_array((int) $request->get('shift'), [1, 2]) ? (int) $request->get('shift') : $s;

        $teams = Team::with(['division', 'members', 'plans.device', 'plans.location', 'plans.tool', 'plans.activity.troubleshoots'])
            ->where('team_date', $date)->where('shift', $shift)->orderBy('division_id')->orderBy('id')->get();

        $plans = $teams->flatMap(function ($t) { return $t->plans; });
        $sum = [
            'teams'   => $teams->count(),
            'plans'   => $plans->count(),
            'finish'  => $plans->whereIn('status', ['finish', 'closed'])->count(),
            'pending' => $plans->whereIn('status', ['pending', 'continue'])->count(),
            'open'    => $plans->where('status', 'open')->count(),
        ];
        $label = Carbon::parse($date)->locale('id')->translatedFormat('l, d F Y');

        return view('act.export.shift', [
            'date' => $date, 'shift' => $shift, 'label' => $label, 'shiftLabel' => RosterService::shiftLabel($shift),
            'teams' => $teams->groupBy(function ($t) { return optional($t->division)->name; }), 'sum' => $sum,
            'wa' => $this->whatsapp($label, RosterService::shiftLabel($shift), $teams, $sum),
        ]);
    }

    private function whatsapp(string $label, string $shiftLabel, $teams, array $sum): string
    {
        $o = ["*LAPORAN AKTIVITAS TIM IT*", $label . ' — ' . $shiftLabel, ''];
        foreach ($teams->groupBy(function ($t) { return optional($t->division)->name; }) as $div => $list) {
            $o[] = '*' . strtoupper($div) . '*';
            foreach ($list as $t) {
                $o[] = '• ' . $t->name . ' (' . ucfirst($t->plan_type) . ')';
                $o[] = '  Pengawas: ' . (optional($t->supervisor)->name ?: '-');
                $o[] = '  Teknisi: ' . $t->technicians->pluck('name')->implode(', ');
                foreach ($t->plans as $i => $p) {
                    $a = $p->activity;
                    $icon = $p->status === 'open' ? '⬜' : (in_array($p->status, ['finish', 'closed']) ? '✅' : '⏳');
                    $where = trim(optional($p->device)->name . ' ' . $p->unit . (optional($p->location)->name ? ' @' . $p->location->name : ''));
                    $o[] = '  ' . ($i + 1) . '. ' . $icon . ' ' . $p->title . ($where ? ' — ' . $where : '') . ($a ? ' [' . $a->code . ']' : '');
                    if ($a) {
                        foreach ($a->lines('done') as $l) { $o[] = '     - ' . $l; }
                        if (in_array($p->status, ['pending', 'continue'])) {
                            foreach ($a->lines('remaining') as $l) { $o[] = '     ! Belum: ' . $l; }
                            $o[] = '     Alasan: ' . $a->reason_label . ' — ' . $a->pending_reason;
                            foreach ($a->lines('next_plan') as $l) { $o[] = '     > Rencana: ' . $l; }
                        }
                    } else {
                        $o[] = '     (belum di-follow-up)';
                    }
                }
            }
            $o[] = '';
        }
        $o[] = 'Ringkasan: ' . $sum['teams'] . ' tim, ' . $sum['plans'] . ' plan — selesai ' . $sum['finish'] . ', pending ' . $sum['pending'] . ', belum follow-up ' . $sum['open'];
        return implode("\n", $o);
    }
}
