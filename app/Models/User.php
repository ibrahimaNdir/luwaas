<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // 👈 important


class User extends Authenticatable
{

    use HasApiTokens, Notifiable;

    protected $fillable = [
        'prenom',
        'nom',
        'telephone',
        'email',
        'password',
        'is_active',
        'user_type',
        'profile',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'profile' => 'array',
    ];

    // Relations
    public function proprietaire()
    {
        return $this->hasOne(Proprietaire::class);


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
