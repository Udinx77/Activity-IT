<?php

namespace App\Models\Act;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $table = 'act_teams';
    protected $guarded = [];
    protected $casts = ['team_date' => 'date'];

    public function division() { return $this->belongsTo(Division::class, 'division_id'); }
    public function members()  { return $this->hasMany(TeamMember::class, 'team_id')->orderBy('role')->orderBy('slot'); }
    public function plans()    { return $this->hasMany(Plan::class, 'team_id')->orderBy('seq')->orderBy('id'); }

    public function getSupervisorAttribute()
    {
        return $this->members->firstWhere('role', 'pengawas');
    }

    public function getTechniciansAttribute()
    {
        return $this->members->where('role', 'teknisi')->values();
    }

    /** Anggota dikunci setelah ada troubleshoot (follow-up) pada salah satu plan. */
    public function isLocked(): bool
    {
        return $this->plans()->whereNotNull('activity_id')->exists();
    }
}
