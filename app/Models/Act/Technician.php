<?php

namespace App\Models\Act;

use Illuminate\Database\Eloquent\Model;

class Technician extends Model
{
    protected $table = 'act_technicians';
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
}
