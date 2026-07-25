<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CdiRate extends Model
{
    protected $fillable = ['rate', 'effective_from'];

    protected $casts = [
        'effective_from' => 'date',
        'rate' => 'float',
    ];
}
