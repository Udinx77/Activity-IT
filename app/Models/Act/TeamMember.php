<?php

namespace App\Models\Act;

use Illuminate\Database\Eloquent\Model;

class TeamMember extends Model
{
    protected $table = 'act_team_members';
    protected $guarded = [];
    protected $casts = ['team_date' => 'date'];

    public function team() { return $this->belongsTo(Team::class, 'team_id'); }
}
