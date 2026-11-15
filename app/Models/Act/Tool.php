<?php

namespace App\Models\Act;

use Illuminate\Database\Eloquent\Model;

class Tool extends Model
{
    protected $table = 'act_tools';
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
}
