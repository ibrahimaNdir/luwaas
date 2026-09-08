<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ActivityLogger
{
    public static function log(string $description, array $properties = [], ?int $userId = null)
    {
        try {
            ActivityLog::create([
                'description' => $description,
                'properties'  => $properties,
                'user_id'     => $userId ?? (Auth::check() ? Auth::user()->id : null),
            ]);
        } catch (\Exception $e) {
            Log::error('ActivityLogger error: '.$e->getMessage());
            throw $e;
        }
    }
}