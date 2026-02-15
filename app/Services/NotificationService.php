<?php

namespace App\Services;

use Kreait\Firebase\Contract\Firestore;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Google\Cloud\Core\Timestamp;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected $messaging;
    protected $firestore;

    public function __construct(Messaging $messaging, Firestore $firestore)
    {
        $this->messaging = $messaging;
        $this->firestore = $firestore;
    }

    /**
     * ✅ Envoie une notification à un utilisateur (FCM + Firestore)
     */
    public function sendToUser($user, $title, $body, $type, array $data = [])
    {
        // 1. Envoyer le Push Notification (FCM)
        $token = $user->fcm_token;
        
        if ($token) {
            try {
                $message = CloudMessage::withTarget('token', $token)
                    ->withNotification(Notification::create($title, $body))
                    ->withData(array_merge(['type' => $type], $data)); // ✅ Données incluses
                
                $this->messaging->send($message);
            } catch (\Exception $e) {
                Log::warning("Erreur FCM user {$user->id}: " . $e->getMessage());
            }
        }

        // 2. ✅ STRUCTURE CORRECTE : users/{userId}/notifications
        try {
            $this->firestore->database()
                ->collection('users')                    // ✅ Collection users
                ->document((string) $user->id)           // ✅ Document userId
                ->collection('notifications')            // ✅ Sous-collection
                ->add([
                    'title' => $title,
                    'body' => $body,
                    'type' => $type,
                    'read' => false,                     // ✅ 'read' (pas 'is_read')
                    'createdAt' => new Timestamp(new \DateTime()), // ✅ Correspond à Flutter
                    'data' => $data,                     // ✅ Données additionnelles
                ]);
        } catch (\Exception $e) {
            Log::error("Erreur Firestore user {$user->id}: " . $e->getMessage());
        }
    }

    /**
     * ✅ BONUS : Envoie à plusieurs users (bailleur + locataire)
     */
    public function sendToMultipleUsers(array $users, $title, $body, $type, array $data = [])
    {
        foreach ($users as $user) {
            $this->sendToUser($user, $title, $body, $type, $data);
        }
    }
}
