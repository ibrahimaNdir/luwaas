<?php

namespace App\Services;

use App\Traits\Loggable;
use App\Contracts\SmsProviderInterface;
use Kreait\Firebase\Contract\Firestore;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Google\Cloud\Core\Timestamp;
use Illuminate\Support\Facades\Log;
use App\Models\NotificationDeadLetter;

class NotificationService
{
<<<<<<< HEAD
    use Loggable;

    protected $messaging;
    protected $firestore;
    protected $sms;
=======
    protected Messaging $messaging;
    protected Firestore $firestore;
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)

    public function __construct(Messaging $messaging, Firestore $firestore, SmsProviderInterface $sms)
    {
        $this->messaging = $messaging;
        $this->firestore = $firestore;
        $this->sms = $sms;
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
            $this->logDev('warning', "NotificationService: Utilisateur null");
            return false;
        }

        $fcmSent = false;
        $firestoreSent = false;
        $maxAttempts = 3;
        $delayMs = 100; // base delay in milliseconds

        // 1. ✅ Envoyer le Push Notification (FCM) avec retry
        $token = $user->fcm_token;
        if ($token) {
<<<<<<< HEAD
            try {
                $message = CloudMessage::withTarget('token', $token)
                    ->withNotification(Notification::create($title, $body))
                    ->withData(array_merge([
                        'type' => $type,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK', // ✅ Pour Flutter
                    ], $data));
                
                $this->messaging->send($message);
                $fcmSent = true;
                $this->logDev('info', "✅ FCM envoyé à user {$user->id}");
            } catch (\Exception $e) {
                $this->logDev('warning', "❌ Erreur FCM user {$user->id}: " . $e->getMessage());
=======
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    $message = CloudMessage::withTarget('token', $token)
                        ->withNotification(Notification::create($title, $body))
                        ->withData(array_merge([
                            'type' => $type,
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK', // ✅ Pour Flutter
                        ], $data));

                    $response = $this->messaging->send($message);

                    // Vérifier les erreurs spécifiques FCM pour détecter les tokens invalides
                    if ($response->getFailureCount() > 0) {
                        foreach ($response->getResponses() as $resp) {
                            $status = $resp->getError()->getStatusCode() ?? '';
                            $messageError = $resp->getError()->getMessage() ?? '';

                            // Tokens invalides ou non enregistrés
                            if ($status === 'NOT_REGISTERED' ||
                                $status === 'INVALID_ARGUMENT' ||
                                str_contains($messageError, 'InvalidRegistration') ||
                                str_contains($messageError, 'NotRegistered')) {
                                $this->markFcmTokenAsInvalid($user->id, $token);
                                Log::info("🔇 Token FCM invalide détecté et marqué pour user {$user->id}");
                                // After marking invalid, no point retrying same token
                                $attempt = $maxAttempts; // will exit loop after this iteration
                                break 2; // break out of both foreach and for loop
                            }
                        }
                    }

                    $fcmSent = true;
                    Log::info("✅ FCM envoyé à user {$user->id} (tentative {$attempt})");
                    break; // Success, exit retry loop
                } catch (\Exception $e) {
                    // Gestion spécifique des erreurs FCM
                    $statusCode = $e->getCode() ?? '';
                    $messageError = $e->getMessage() ?? '';

                    if ($statusCode === 'NOT_REGISTERED' ||
                        $statusCode === 'INVALID_ARGUMENT' ||
                        str_contains($messageError, 'NotRegistered') ||
                        str_contains($messageError, 'InvalidRegistration')) {
                        $this->markFcmTokenAsInvalid($user->id, $token);
                        Log::info("🔇 Token FCM invalide détecté et marqué pour user {$user->id}");
                        // Break out of retry loop as token is invalid
                        break;
                    } else {
                        Log::warning("❌ Erreur FCM user {$user->id} (tentative {$attempt}): " . $e->getMessage());
                        // If not last attempt, wait before retrying
                        if ($attempt < $maxAttempts) {
                            usleep($delayMs * 1000); // convert ms to microseconds
                            $delayMs *= 2; // exponential backoff
                        }
                    }
                }
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
            }
        } else {
            $this->logDev('info', "⚠️ User {$user->id} n'a pas de FCM token");
        }

<<<<<<< HEAD
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
            $this->logDev('info', "✅ Notification Firestore sauvegardée pour user {$user->id}");
        } catch (\Exception $e) {
            Log::error("❌ Erreur Firestore user {$user->id}: " . $e->getMessage());
=======
        // 2. ✅ Sauvegarder dans Firestore (TOUJOURS, même sans FCM token) avec retry
        $maxAttemptsFs = 3;
        $delayMsFs = 100;
        for ($attempt = 1; $attempt <= $maxAttemptsFs; $attempt++) {
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
                Log::info("✅ Notification Firestore sauvegardée pour user {$user->id} (tentative {$attempt})");
                break; // Success, exit retry loop
            } catch (\Exception $e) {
                Log::error("❌ Erreur Firestore user {$user->id} (tentative {$attempt}): " . $e->getMessage());
                // If not last attempt, wait before retrying
                if ($attempt < $maxAttemptsFs) {
                    usleep($delayMsFs * 1000);
                    $delayMsFs *= 2;
                }
            }
        }

        // If both FCM and Firestore failed, store in dead letter queue
        if (!$fcmSent && !$firestoreSent) {
            $this->storeInDeadLetter($user, $title, $body, $type, $data, $maxAttempts + $maxAttemptsFs);
            Log::warning("⚠️ Notification stockée dans la file de lettres mortes pour user {$user->id}");
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
        }

        return $fcmSent || $firestoreSent; // ✅ Retourne true si au moins une méthode a fonctionné
    }

    /**
     * Marque un token FCM comme invalide pour éviter les futurs échecs
     *
     * @param int $userId
     * @param string $token
     * @return void
     */
    private function markFcmTokenAsInvalid(int $userId, string $token): void
    {
        try {
            // Mettre à jour l'utilisateur pour NULLifier le token FCM
            // Ceci évitera les futures tentatives d'envoi avec ce token invalide
            $userModel = \App\Models\User::find($userId);
            if ($userModel && $userModel->fcm_token === $token) {
                $userModel->update(['fcm_token' => null]);
                Log::debug("Token FCM invalidé pour user {$userId}");
            }

            // Optionnel: incrémenter un métrique ou logger pour monitoring
            // Vous pourriez aussi ajouter à une table de nettoyage périodique
        } catch (\Exception $e) {
            Log::warning("Échec lors de l'invalidation du token FCM pour user {$userId}: " . $e->getMessage());
        }
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

<<<<<<< HEAD
        $this->logDev('info', "📊 Notifications envoyées : {$successCount}/{count($users)}");
        
=======
        Log::info("📊 Notifications envoyées : {$successCount}/{count($users)}");

>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
        return $successCount;
    }

    /**
     * Envoie un SMS via le fournisseur configuré
     */
    public function sendSms(string $to, string $message): bool
    {
        try {
            return $this->sms->send($to, $message);
        } catch (\Exception $e) {
            Log::error("❌ Erreur lors de l'envoi du SMS à {$to}: " . $e->getMessage());
            return false;
        }
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

            $this->logDev('info', "✅ Notification {$notificationId} marquée comme lue pour user {$userId}");
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

            $this->logDev('info', "✅ {$count} notifications marquées comme lues pour user {$userId}");
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

            $this->logDev('info', "✅ Notification {$notificationId} supprimée pour user {$userId}");
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

    /**
     * Stocke une notification échouée dans la file de lettres mortes
     *
     * @param \App\Models\User $user
     * @param string $title
     * @param string $body
     * @param string $type
     * @param array $data
     * @param int $totalAttempts Nombre total de tentatives effectuées
     * @return void
     */
    private function storeInDeadLetter($user, $title, $body, $type, array $data, int $totalAttempts): void
    {
        try {
            NotificationDeadLetter::create([
                'user_id' => $user->id,
                'title' => $title,
                'body' => $body,
                'type' => $type,
                'data' => $data,
                'attempts' => $totalAttempts,
                'failed_at' => new \DateTime(),
            ]);
            Log::debug("Notification stockée dans la file de lettres mortes pour user {$user->id}");
        } catch (\Exception $e) {
            Log::error("Échec de stockage dans la file de lettres mortes: " . $e->getMessage());
        }
    }
}