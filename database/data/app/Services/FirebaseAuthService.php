<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;

class FirebaseAuthService
{
    protected $auth;
    
    public function __construct()
    {
        $factory = (new Factory)
            ->withServiceAccount(config('services.firebase.credentials'));
        
        $this->auth = $factory->createAuth();
    }
    
    /**
     * Crée un Custom Token Firebase pour un utilisateur
     */
    public function createCustomToken($userId, array $claims = [])
    {
        try {
            $customToken = $this->auth->createCustomToken(
                (string) $userId,
                $claims
            );
            
            return $customToken->toString();
        } catch (\Exception $e) {
            Log::error('Erreur création Firebase token: ' . $e->getMessage());
            throw $e;
        }
    }
}