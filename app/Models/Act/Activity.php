<?php

namespace App\Models\Act;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use SoftDeletes;

    protected $table = 'act_activities';
    protected $guarded = [];
    protected $casts = ['activity_date' => 'date'];

    public function division()   { return $this->belongsTo(Division::class, 'division_id'); }
    public function shift()      { return $this->belongsTo(Shift::class, 'shift_id'); }
    public function device()     { return $this->belongsTo(Device::class, 'device_id'); }
    public function location()   { return $this->belongsTo(Location::class, 'location_id'); }
    public function supervisor() { return $this->belongsTo(Supervisor::class, 'supervisor_id'); }
    public function tool()       { return $this->belongsTo(Tool::class, 'tool_id'); }
    public function logs()       { return $this->hasMany(ActivityLog::class, 'activity_id'); }
    public function team()       { return $this->belongsTo(Team::class, 'team_id'); }
    public function troubleshoots() { return $this->hasMany(Troubleshoot::class, 'activity_id')->orderBy('seq')->orderBy('id'); }

    const REASONS = [
        'tidak_bisa' => 'Tidak bisa dikerjakan',
        'kendala'    => 'Ada kendala saat mengerjakan',
    ];

    public function getReasonLabelAttribute(): ?string
    {
        return self::REASONS[$this->pending_reason_type] ?? null;
    }

    protected static $v2 = null;

    /** True bila tabel v2 (act_troubleshoots, dst) sudah diimport. Fitur lama tetap jalan tanpa SQL v2. */
    public static function v2(): bool
    {
        if (static::$v2 === null) { static::$v2 = \Illuminate\Support\Facades\Schema::hasTable('act_troubleshoots'); }
        return static::$v2;
    }

    /** Baris troubleshoot per jenis: done | remaining | next_plan */
    public function lines(string $kind): array
    {
        if (!static::v2()) { return []; }
        return $this->troubleshoots->where('kind', $kind)->pluck('text')->values()->all();
    }

    public function technicians()
    {
        return $this->belongsToMany(Technician::class, 'act_activity_technicians', 'activity_id', 'technician_id');
    }

    /** Kode berikutnya per prefix: OP001, AL001, ONB001, IF001, PD001 (4 digit jika > 999). */
    public static function nextCode(string $prefix): string
    {
        $prefix = strtoupper($prefix);
        $last = static::withTrashed()
            ->where('code', 'REGEXP', '^' . $prefix . '[0-9]+$')
            ->orderByRaw('CAST(SUBSTRING(code, ' . (strlen($prefix) + 1) . ') AS UNSIGNED) DESC')
            ->value('code');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad((string) $next, max(3, strlen((string) $next)), '0', STR_PAD_LEFT);
    }

    /** ID Induk efektif: parent_code, atau kode sendiri bila ini baris pertama. */
    public function getRootCodeAttribute(): string
    {
        return $this->parent_code ?: $this->code;
    }

    /** Semua baris dalam satu rantai (root + lanjutannya), urut tanggal. */
    public function chain()
    {
        $root = $this->root_code;
        return static::where(function ($q) use ($root) {
                $q->where('code', $root)->orWhere('parent_code', $root);
            })
            ->orderBy('activity_date')->orderBy('id')->get();
    }

    /**
     * Rantai pending yang belum Closed.
     * Hasil: collection objek {root_code, first, last, count, age_days}
     */
    public static function activeChains()
    {
        $rows = static::with(['division', 'supervisor', 'location', 'device'])
            ->whereIn('status', ['pending', 'continue', 'closed'])
            ->orderBy('activity_date')->orderBy('id')->get();

        return $rows->groupBy(function ($r) { return $r->parent_code ?: $r->code; })
            ->filter(function ($g) { return !$g->contains('status', 'closed'); })
            ->map(function ($g, $root) {
                $first = $g->firstWhere('code', $root) ?: $g->first();
                return (object) [
                    'root_code' => $root,
                    'first'     => $first,
                    'last'      => $g->last(),
                    'count'     => $g->count(),
                    'age_days'  => (int) $first->activity_date->diffInDays(\App\Services\Act\RosterService::today()),
                ];
            })->values();
    }

    /** Data pilihan untuk form (dropdown / checkbox). */
    public static function lookups(): array
    {
        return [
            'divisions'   => Division::where('is_active', 1)->orderBy('id')->get(),
            'shifts'      => Shift::where('is_active', 1)->orderBy('id')->get(),
            'devices'     => Device::where('is_active', 1)->orderBy('name')->get(),
            'locations'   => Location::where('is_active', 1)->orderBy('name')->get(),
            'tools'       => Tool::where('is_active', 1)->orderBy('name')->get(),
            'supervisors' => Supervisor::where('is_active', 1)->orderBy('name')->get(),
            'technicians' => Technician::where('is_active', 1)->orderBy('name')->get(),
            'units'       => static::whereNotNull('unit')->where('unit', '<>', '')->distinct()->orderBy('unit')->limit(2000)->pluck('unit'),
        ];
    }
}
