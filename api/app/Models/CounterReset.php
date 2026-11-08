<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A moment the days-without counter went back to zero (or first started).
 */
class CounterReset extends Model
{
    protected $fillable = ['reset_at'];

    protected $casts = [
        'reset_at' => 'datetime',
    ];
}
