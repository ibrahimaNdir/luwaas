<?php

namespace App\Observers;

use App\Models\Proprietaire;
use App\Models\User;
use App\Services\NotificationService;

class ProprietaireObserver
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the Proprietaire "created" event.
     */
    public function created(Proprietaire $proprietaire): void
    {
        $user = $proprietaire->user;
        $userType = $user->locataire ? 'both' : 'proprietaire';
        $user->update(['user_type' => $userType]);

        // If this is the second profile (i.e., user now has both), send notification
        if ($userType === 'both') {
            $this->notificationService->sendToUser(
                $user,
                'Votre compte est maintenant complet',
                'Vous avez maintenant accès aux deux rôles : propriétaire et locataire. Vous pouvez gérer vos biens et chercher un logement depuis le même compte.',
                'role_added'
            );
        }
    }

    /**
     * Handle the Proprietaire "deleted" event.
     */
    public function deleted(Proprietaire $proprietaire): void
    {
        $user = $proprietaire->user;
        $userType = $user->locataire ? 'locataire' : null;
        $user->update(['user_type' => $userType]);
    }
}
