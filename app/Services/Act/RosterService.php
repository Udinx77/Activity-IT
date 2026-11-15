<?php

namespace App\Services\Act;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Roster & aturan shift.
 *  - Roster IDA: tabel data_attendances (date_schedule, nik, shift_description) + data_personnels
 *  - Kode roster: mengandung H = Shift 1 (07:00-17:00), mengandung M = Shift 2 (17:01-06:59); O, CP, CT, S = tidak bekerja
 *    (aturan LIKE yang sama dengan halaman Attendance/dashboard IDA)
 *  - Zona waktu kerja: WITA (config IDA masih UTC, jadi dihitung di sini)
 */
class RosterService
{
    const TZ = 'Asia/Makassar';

    public static function now(): Carbon
    {
        return Carbon::now(self::TZ);
    }

    /** Tanggal WITA hari ini sebagai Carbon di zona aplikasi (agar selisih hari konsisten dengan kolom tanggal). */
    public static function today(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', self::now()->toDateString())->startOfDay();
    }

    /** [tanggal, shift] yang sedang berjalan. Jam 00:00-06:59 masih Shift 2 tanggal kemarin. */
    public static function context(): array
    {
        $n = self::now();
        $m = $n->hour * 60 + $n->minute;
        if ($m >= 420 && $m <= 1020) { return [$n->toDateString(), 1]; }
        if ($m > 1020) { return [$n->toDateString(), 2]; }
        return [$n->copy()->subDay()->toDateString(), 2];
    }

    public static function shiftLabel(int $shift): string
    {
        return $shift === 1 ? 'Shift 1 (07:00–17:00)' : 'Shift 2 (17:01–06:59)';
    }

    public static function shiftCode(int $shift): string
    {
        return $shift === 1 ? 'H' : 'M';
    }

    /** Jabatan Supervisor SCH / UNS -> 'schedule' | 'unschedule' | null (bukan pengawas). */
    public static function supervisorType(?string $position): ?string
    {
        $p = mb_strtolower(trim((string) $position));
        if ($p === '' || (strpos($p, 'supervisor') === false && strpos($p, 'spv') === false)) { return null; }
        if (strpos($p, 'uns') !== false) { return 'unschedule'; }   // cek 'uns' dulu: "unschedule" mengandung "sch"
        if (strpos($p, 'sch') !== false) { return 'schedule'; }
        return null;
    }

    public static function displayName($nick, $full): string
    {
        $nick = trim((string) $nick);
        return $nick !== '' ? $nick : trim((string) $full);
    }

    /** Manpower yang bekerja pada tanggal + shift (dari roster). */
    public static function pool(string $date, int $shift): Collection
    {
        $rows = DB::table('data_attendances as a')
            ->join('data_personnels as p', 'p.nik', '=', 'a.nik')
            ->whereDate('a.date_schedule', $date)
            ->whereRaw('LOWER(a.shift_description) LIKE ?', ['%' . strtolower(self::shiftCode($shift)) . '%']) // sama dengan dashboard IDA (LIKE %h% / %m%)
            ->select('p.nik', 'p.full_name', 'p.nick_name', 'p.position')
            ->orderBy('p.full_name')->get();

        return $rows->unique('nik')->map(function ($r) {
            $r->name = self::displayName($r->nick_name, $r->full_name);
            $r->sup_type = self::supervisorType($r->position);
            return $r;
        })->values();
    }

    /** Ringkasan kode roster pada satu tanggal (diagnostik): ['H' => 10, 'M' => 5, ...] */
    public static function codes(string $date): array
    {
        return DB::table('data_attendances')->whereDate('date_schedule', $date)
            ->selectRaw('UPPER(TRIM(shift_description)) as code, COUNT(*) as n')
            ->groupBy(DB::raw('UPPER(TRIM(shift_description))'))->orderBy('code')->pluck('n', 'code')->all();
    }
}
