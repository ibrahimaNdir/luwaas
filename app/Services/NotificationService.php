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
     * 
     * @param \App\Models\User $user L'utilisateur destinataire
     * @param string $title Titre de la notification
     * @param string $body Corps du message
     * @param string $type Type de notification (demande_recue, demande_acceptee, etc.)
     * @param array $data Données additionnelles (optionnel)
     * @return bool Succès ou échec
     */
    public function sendToUser($user, $title, $body, $type, array $data = [])
    {
        if (!$user) {
            Log::warning("NotificationService: Utilisateur null");
            return false;
        }

        $fcmSent = false;
        $firestoreSent = false;

        // 1. ✅ Envoyer le Push Notification (FCM)
        $token = $user->fcm_token;
        
        if ($token) {
            try {
                $message = CloudMessage::withTarget('token', $token)
                    ->withNotification(Notification::create($title, $body))
                    ->withData(array_merge([
                        'type' => $type,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK', // ✅ Pour Flutter
                    ], $data));
                
                $this->messaging->send($message);
                $fcmSent = true;
                Log::info("✅ FCM envoyé à user {$user->id}");
            } catch (\Exception $e) {
                Log::warning("❌ Erreur FCM user {$user->id}: " . $e->getMessage());
            }
        } else {
            Log::info("⚠️ User {$user->id} n'a pas de FCM token");
        }

        // 2. ✅ Sauvegarder dans Firestore (TOUJOURS, même sans FCM token)
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
            
            $firestoreSent = true;
            Log::info("✅ Notification Firestore sauvegardée pour user {$user->id}");
        } catch (\Exception $e) {
            Log::error("❌ Erreur Firestore user {$user->id}: " . $e->getMessage());
        }

        return $fcmSent || $firestoreSent; // ✅ Retourne true si au moins une méthode a fonctionné
    }

    /**
     * ✅ Envoie à plusieurs users (bailleur + locataire)
     * 
     * @param array $users Tableau d'utilisateurs
     * @param string $title
     * @param string $body
     * @param string $type
     * @param array $data
     * @return int Nombre de notifications envoyées avec succès
     */
    public function sendToMultipleUsers(array $users, $title, $body, $type, array $data = [])
    {
        $successCount = 0;

        foreach ($users as $user) {
            if ($this->sendToUser($user, $title, $body, $type, $data)) {
                $successCount++;
            }
        }

        Log::info("📊 Notifications envoyées : {$successCount}/{count($users)}");
        
        return $successCount;
    }

    /**
     * ✅ NOUVEAU : Marquer une notification comme lue
     * 
     * @param int $userId
     * @param string $notificationId
     * @return bool
     */
    public function markAsRead($userId, $notificationId)
    {
        try {
            $this->firestore->database()
                ->collection('users')
                ->document((string) $userId)
                ->collection('notifications')
                ->document($notificationId)
                ->update([
                    ['path' => 'read', 'value' => true]
                ]);

            Log::info("✅ Notification {$notificationId} marquée comme lue pour user {$userId}");
            return true;
        } catch (\Exception $e) {
            Log::error("❌ Erreur markAsRead: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ NOUVEAU : Marquer toutes les notifications comme lues
     * 
     * @param int $userId
     * @return int Nombre de notifications mises à jour
     */
    public function markAllAsRead($userId)
    {
        try {
            $notifications = $this->firestore->database()
                ->collection('users')
                ->document((string) $userId)
                ->collection('notifications')
                ->where('read', '=', false)
                ->documents();

            $count = 0;
            foreach ($notifications as $notification) {
                $notification->reference()->update([
                    ['path' => 'read', 'value' => true]
                ]);
                $count++;
            }

            Log::info("✅ {$count} notifications marquées comme lues pour user {$userId}");
            return $count;
        } catch (\Exception $e) {
            Log::error("❌ Erreur markAllAsRead: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ NOUVEAU : Supprimer une notification
     * 
     * @param int $userId
     * @param string $notificationId
     * @return bool
     */
    public function deleteNotification($userId, $notificationId)
    {
        try {
            $this->firestore->database()
                ->collection('users')
                ->document((string) $userId)
                ->collection('notifications')
                ->document($notificationId)
                ->delete();

            Log::info("✅ Notification {$notificationId} supprimée pour user {$userId}");
            return true;
        } catch (\Exception $e) {
            Log::error("❌ Erreur deleteNotification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ NOUVEAU : Compter les notifications non lues
     * 
     * @param int $userId
     * @return int
     */
    public function getUnreadCount($userId)
    {
        try {
            $notifications = $this->firestore->database()
                ->collection('users')
                ->document((string) $userId)
                ->collection('notifications')
                ->where('read', '=', false)
                ->documents();

            return count($notifications->rows());
        } catch (\Exception $e) {
            Log::error("❌ Erreur getUnreadCount: " . $e->getMessage());
            return 0;
        }
    }
}