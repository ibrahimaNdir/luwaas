<?php

namespace App\Services;

use Kreait\Firebase\Contract\Firestore;
use Google\Cloud\Core\Timestamp;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected $firestore;

    public function __construct(Firestore $firestore)
    {
        $this->firestore = $firestore;
    }

    /**
     * Enregistre une notification in-app pour un utilisateur (Firestore uniquement)
     */
    public function sendToUser($user, $title, $body, $type, array $data = [])
    {
        if (!$user) {
            Log::warning("NotificationService: Utilisateur null");
            return false;
        }

        try {
            $this->firestore->database()
                ->collection('users')
                ->document((string) $user->id)
                ->collection('notifications')
                ->add([
                    'title' => $title,
                    'body' => $body,
                    'type' => $type,
                    'read' => false,
                    'createdAt' => new Timestamp(new \DateTime()),
                    'data' => $data,
                ]);

            Log::info("✅ Notification Firestore sauvegardée pour user {$user->id}");
            return true;
        } catch (\Exception $e) {
            Log::error("❌ Erreur Firestore user {$user->id}: " . $e->getMessage());
            return false;
        }
    }

    public function sendToMultipleUsers(array $users, $title, $body, $type, array $data = [])
    {
        $successCount = 0;

        foreach ($users as $user) {
            if ($this->sendToUser($user, $title, $body, $type, $data)) {
                $successCount++;
            }
        }

        Log::info("📊 Notifications envoyées : {$successCount}/" . count($users));

        return $successCount;
    }

    // markAsRead(), markAllAsRead(), deleteNotification(), getUnreadCount()
    // restent identiques, aucun changement nécessaire
}