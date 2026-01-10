<?php

namespace App\Services;

use Kreait\Firebase\Contract\Firestore;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Google\Cloud\Core\Timestamp; // <--- IMPORTER CA

class NotificationService
{
    protected $messaging;
    protected $firestore;

    public function __construct(Messaging $messaging, Firestore $firestore)
    {
        $this->messaging = $messaging;
        $this->firestore = $firestore;
    }

    public function sendToUser($user, $title, $body, $type)
    {
        // 1. Envoyer le Push Notification (FCM)
        $token = $user->fcm_token; 
        
        if ($token) {
            try {
                $message = CloudMessage::withTarget('token', $token)
                    ->withNotification(Notification::create($title, $body))
                    ->withData(['type' => $type]);
                
                $this->messaging->send($message);
            } catch (\Exception $e) {
                // On ignore les erreurs d'envoi FCM
            }
        }

        // 2. Sauvegarder dans Firestore (Historique)
        try {
            $this->firestore->database()->collection('notifications')->add([
                'user_id' => (string) $user->id, // <--- Forcer en String c'est mieux
                'title' => $title,
                'body' => $body,
                'type' => $type,
                'is_read' => false,
                'created_at' => new Timestamp(new \DateTime()), // <--- Format Timestamp Firestore
            ]);
        } catch (\Exception $e) {
             // Log::error("Erreur Firestore: " . $e->getMessage());
        }
    }
}
