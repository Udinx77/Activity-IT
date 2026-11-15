<?php

namespace App\Models\Act;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $table = 'act_locations';
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
}
