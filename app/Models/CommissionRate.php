<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class CommissionRate extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'commission_rates';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'gateway',
        'operator',
        'operation_type',
        'rate_percent',
        'valid_from',
        'valid_to',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'valid_from' => 'date',
        'valid_to'   => 'date',
        'rate_percent' => 'float',
    ];

    /**
     * Get the rate as a decimal (e.g., 1.5% -> 0.015).
     *
     * @return float
     */
    public function rateAsDecimal(): float
    {
        return $this->rate_percent / 100;
    }

    /**
     * Check if the rate is valid for a given date.
     *
     * @param  string  $date  Date in Y-m-d format or DateTimeInterface
     * @return bool
     */
    public function isValidForDate($date): bool
    {
        if ($date instanceof \DateTimeInterface) {
            $date = $date->format('Y-m-d');
        }

        $validFrom = $this->valid_from ? $this->valid_from->format('Y-m-d') : null;
        $validTo   = $this->valid_to ? $this->valid_to->format('Y-m-d') : null;

        // Check if date is on or after valid_from
        if ($validFrom && $date < $validFrom) {
            return false;
        }

        // Check if date is on or before valid_to (if valid_to is set)
        if ($validTo && $date > $validTo) {
            return false;
        }

        return true;
    }
}
