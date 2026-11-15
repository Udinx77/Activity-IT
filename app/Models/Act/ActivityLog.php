<?php

namespace App\Models\Act;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'act_activity_logs';
    protected $guarded = [];
    protected $casts = ['changes' => 'array'];

    public function user() { return $this->belongsTo(\App\Models\User::class, 'user_id'); }
}
