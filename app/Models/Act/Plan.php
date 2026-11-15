<?php

namespace App\Models\Act;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'act_plans';
    protected $guarded = [];

    public function team()     { return $this->belongsTo(Team::class, 'team_id'); }
    public function device()   { return $this->belongsTo(Device::class, 'device_id'); }
    public function location() { return $this->belongsTo(Location::class, 'location_id'); }
    public function tool()     { return $this->belongsTo(Tool::class, 'tool_id'); }
    public function activity() { return $this->belongsTo(Activity::class, 'activity_id'); }

    public function isOpen(): bool { return $this->status === 'open'; }
}
