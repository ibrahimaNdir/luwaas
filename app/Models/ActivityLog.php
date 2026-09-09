<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_log';

    protected $fillable = [
        'description',
        'properties',
        'user_id',
        'subject_type',
        'subject_id'
    ];

    protected $casts = [
        'properties' => 'array',
    ];
}
