<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
   
     use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'prenom',
        'nom',
        'telephone',
        'email',
        'password',
        'is_active',
        'user_type',
        'profile',

        // 🆕 Champs OTP
        'phone_verified_at',
        'phone_otp',
        'phone_otp_expires_at',
        'otp_attempts'

    ];

    protected $hidden = [
        'password',
        'remember_token',
        'phone_otp', // 🔒 jamais exposé dans les réponses JSON
        'otp_attempts', // 🔒 jamais exposé dans les réponses JSON
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'profile'             => 'array',
        'phone_verified_at'   => 'datetime',
        'phone_otp_expires_at' => 'datetime',
        'otp_attempts'        => 'integer',
    ];

    // 🆕 Helpers OTP
    public function hasVerifiedPhone(): bool
    {
        return !is_null($this->phone_verified_at);
    }

    public function markPhoneAsVerified(): void
    {
        $this->forceFill([
            'phone_verified_at'    => now(),
            'phone_otp'           => null,
            'phone_otp_expires_at' => null,
        ])->save();
    }

    public function isOtpExpired(): bool
    {
        return $this->phone_otp_expires_at === null
            || now()->isAfter($this->phone_otp_expires_at);
    }

    // Relations (inchangées)
    public function proprietaire()
    {
        return $this->hasOne(Proprietaire::class, 'user_id');
    }

    public function locataire()
    {
        return $this->hasOne(Locataire::class);
    }

    public function admin()
    {
        return $this->hasOne(Admin::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
}
