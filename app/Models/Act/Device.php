<?php

namespace App\Models\Act;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $table = 'act_devices';
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
}
