<?php

namespace App\Models\Act;

use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    protected $table = 'act_divisions';
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
}
