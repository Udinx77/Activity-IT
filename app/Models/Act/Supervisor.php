<?php

namespace App\Models\Act;

use Illuminate\Database\Eloquent\Model;

class Supervisor extends Model
{
    protected $table = 'act_supervisors';
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
}
