<?php

namespace App\Listeners;

use App\Events\NouveauProprietaireInscrit;
use App\Models\Admin;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class NotifierAdminNouvelInscrit
{
    public function __construct(protected NotificationService $notificationService) {}

    public function handle(NouveauProprietaireInscrit $event): void
    {
        $user = $event->user;

        // Récupérer tous les admins actifs
        $admins = Admin::with('user')->get();

        foreach ($admins as $admin) {
            if (!$admin->user) continue;

            $this->notificationService->sendToUser(
                $admin->user,
                '📱 Nouveau bailleur inscrit',
                "{$user->prenom} {$user->nom} vient de s'inscrire sur Luwaas (essai gratuit 15 jours).",
                'admin_nouvel_inscrit',
                ['user_id' => $user->id, 'user_type' => 'proprietaire']
            );
        }

        Log::info("🔔 Admins notifiés : nouveau bailleur #{$user->id}");
    }
}
