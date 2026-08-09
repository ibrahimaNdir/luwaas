<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionRate extends Model
{
    protected $fillable = [
        'operator',
        'rate_percent',
        'fixed_fee',
        'valid_from',
        'valid_to',
    ];
}
