<?php

namespace App\Models\Act;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $table = 'act_shifts';
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
}
