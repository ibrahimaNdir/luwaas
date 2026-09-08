<?php

namespace App\Observers;

use App\Models\Locataire;
use App\Models\User;
use App\Services\NotificationService;

class LocataireObserver
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the Locataire "created" event.
     */
    public function created(Locataire $locataire): void
    {
        $user = $locataire->user;
        $userType = $user->proprietaire ? 'both' : 'locataire';
        $user->update(['user_type' => $userType]);

        // If this is the second profile (i.e., user now has both), send notification
        if ($userType === 'both') {
            $this->notificationService->sendToUser(
                $user,
                'Votre compte est maintenant complet',
                'Vous avez maintenant accès aux deux rôles : locataire et propriétaire. Vous pouvez chercher un logement et gérer vos biens depuis le même compte.',
                'role_added'
            );
        }
    }

    /**
     * Handle the Locataire "deleted" event.
     */
    public function deleted(Locataire $locataire): void
    {
        $user = $locataire->user;
        $userType = $user->proprietaire ? 'proprietaire' : null;
        $user->update(['user_type' => $userType]);
    }
}
