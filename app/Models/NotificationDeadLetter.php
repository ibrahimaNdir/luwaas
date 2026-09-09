<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationDeadLetter extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'type',
        'data',
        'attempts',
        'failed_at',
    ];

    protected $casts = [
        'data' => 'array',
        'failed_at' => 'datetime',
    ];
}
