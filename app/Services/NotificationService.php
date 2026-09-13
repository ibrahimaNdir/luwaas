<?php

namespace App\Services;

use App\Traits\Loggable;
use App\Contracts\SmsProviderInterface;
use Kreait\Firebase\Contract\Firestore;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MessageData;
use Kreait\Firebase\Messaging\Notification;
use Google\Cloud\Core\Timestamp;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\NotificationDeadLetter;

class NotificationService
{
    use Loggable;

    protected Messaging $messaging;
    protected Firestore $firestore;
    protected $sms;

    // FCM error statuses that indicate invalid token
    private const INVALID_FCM_STATUSES = ['NOT_REGISTERED', 'INVALID_ARGUMENT'];
    private const INVALID_FCM_MESSAGE_PATTERNS = ['InvalidRegistration', 'NotRegistered'];

    public function __construct(Messaging $messaging, Firestore $firestore, SmsProviderInterface $sms)
    {
        $this->messaging = $messaging;
        $this->firestore = $firestore;
        $this->sms = $sms;
    }

    /**
     * Send a notification to a user (FCM + Firestore)
     *
     * @param \App\Models\User $user The recipient user
     * @param string $title Notification title
     * @param string $body Notification body
     * @param string $type Notification type (e.g., demande_recue, demande_acceptee)
     * @param array $data Additional data (optional)
     * @return bool Success if at least one method succeeded
     */
    public function sendToUser(User $user, string $title, string $body, string $type, array $data = []): bool
    {
        if (!$user) {
            $this->logDev('warning', "NotificationService: Utilisateur nul");
            return false;
        }

        $fcmSent = false;
        $firestoreSent = false;
        $maxAttempts = 3;
        $delayMs = 100; // base delay in milliseconds

        // 1. Send Push Notification (FCM) with retry
        $token = $user->fcm_token;
        if ($token) {
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    $message = CloudMessage::withToken($token)
                        ->withNotification(Notification::create($title, $body))
                        ->withData(MessageData::fromArray(array_merge([
                            'type' => $type,
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ], $data)));

                    $response = $this->messaging->send($message);

                    if ($response->getFailureCount() > 0) {
                        foreach ($response->getResponses() as $resp) {
                            $status = $resp->getError()->getStatusCode() ?? '';
                            $messageError = $resp->getError()->getMessage() ?? '';

                            if (in_array($status, self::INVALID_FCM_STATUSES, true) ||
                                preg_match('/' . implode('|', self::INVALID_FCM_MESSAGE_PATTERNS) . '/', $messageError)) {
                                $this->markFcmTokenAsInvalid($user->id, $token);
                                $this->logDev('info', "Token FCM invalide détecté et marqué pour l'utilisateur {$user->id}");
                                $attempt = $maxAttempts;
                                break 2;
                            }
                        }
                    }

                    $fcmSent = true;
                    $this->logDev('info', "FCM envoyé à l'utilisateur {$user->id} (tentative {$attempt})");
                    break;
                } catch (\Exception $e) {
                    $statusCode = $e->getCode() ?? '';
                    $messageError = $e->getMessage() ?? '';

                    if (in_array($statusCode, self::INVALID_FCM_STATUSES, true) ||
                        preg_match('/' . implode('|', self::INVALID_FCM_MESSAGE_PATTERNS) . '/', $messageError)) {
                        $this->markFcmTokenAsInvalid($user->id, $token);
                        $this->logDev('info', "Token FCM invalide détecté et marqué pour l'utilisateur {$user->id}");
                        break;
                    } else {
                        Log::warning("Erreur FCM pour l'utilisateur {$user->id} (tentative {$attempt}) : " . $e->getMessage());
                        if ($attempt < $maxAttempts) {
                            usleep($delayMs * 1000);
                            $delayMs *= 2;
                        }
                    }
                }
            }
        } else {
            $this->logDev('info', "L'utilisateur {$user->id} n'a pas de token FCM");
        }

        // 2. Save to Firestore (ALWAYS, even without FCM token) with retry
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
                $this->logDev('info', "Firestore notification enregistrée pour l'utilisateur {$user->id} (tentative {$attempt})");
                break;
            } catch (\Exception $e) {
                Log::error("Erreur Firestore pour l'utilisateur {$user->id} (tentative {$attempt}) : " . $e->getMessage());
                if ($attempt < $maxAttemptsFs) {
                    usleep($delayMsFs * 1000);
                    $delayMsFs *= 2;
                }
            }
        }

        // If both FCM and Firestore failed, store in dead letter queue
        if (!$fcmSent && !$firestoreSent) {
            $this->storeInDeadLetter($user, $title, $body, $type, $data, $maxAttempts + $maxAttemptsFs);
            Log::warning("Notification stockée dans la file d'attente de lettres mortes pour l'utilisateur {$user->id}");
        }

        return $fcmSent || $firestoreSent;
    }

    private function markFcmTokenAsInvalid(int $userId, string $token): void
    {
        try {
            $userModel = User::find($userId);
            if ($userModel && $userModel->fcm_token === $token) {
                $userModel->update(['fcm_token' => null]);
                Log::debug("Jeton FCM invalidé pour l'utilisateur {$userId}");
            }
        } catch (\Exception $e) {
            Log::warning("Échec de l'invalidation du jeton FCM pour l'utilisateur {$userId} : " . $e->getMessage());
        }
    }

    public function sendToMultipleUsers(array $users, $title, $body, $type, array $data = []): int
    {
        $successCount = 0;

        foreach ($users as $user) {
            if ($this->sendToUser($user, $title, $body, $type, $data)) {
                $successCount++;
            }
        }

        $this->logDev('info', "Notifications envoyées : {$successCount}/" . count($users));

        return $successCount;
    }

    public function sendSms(string $to, string $message): bool
    {
        try {
            return $this->sms->send($to, $message);
        } catch (\Exception $e) {
            Log::warning("Erreur lors de l'envoi du SMS à {$to} : " . $e->getMessage());
            return false;
        }
    }

    public function markAsRead(int $userId, string $notificationId): bool
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

            $this->logDev('info', "Notification {$notificationId} marquée comme lue pour l'utilisateur {$userId}");
            return true;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la marquage de la notification comme lue : " . $e->getMessage());
            return false;
        }
    }

    public function markAllAsRead(int $userId): int
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

            $this->logDev('info', "{$count} notifications marquées comme lues pour l'utilisateur {$userId}");
            return $count;
        } catch (\Exception $e) {
            Log::error("Erreur lors du marquage de toutes les notifications comme lues : " . $e->getMessage());
            return 0;
        }
    }

    public function deleteNotification(int $userId, string $notificationId): bool
    {
        try {
            $this->firestore->database()
                ->collection('users')
                ->document((string) $userId)
                ->collection('notifications')
                ->document($notificationId)
                ->delete();

            $this->logDev('info', "Notification {$notificationId} supprimée pour l'utilisateur {$userId}");
            return true;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la suppression de la notification : " . $e->getMessage());
            return false;
        }
    }

    public function getUnreadCount(int $userId): int
    {
        try {
            $notifications = $this->firestore->database()
                ->collection('users')
                ->document((string) $userId)
                ->collection('notifications')
                ->where('read', '=', false)
                ->documents();

            return count($notifications);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération du nombre de notifications non lues : " . $e->getMessage());
            return 0;
        }
    }

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
            Log::debug("Notification stockée dans la file d'attente de lettres mortes pour l'utilisateur {$user->id}");
        } catch (\Exception $e) {
            Log::error("Échec lors du stockage dans la file d'attente de lettres mortes : " . $e->getMessage());
        }
    }
}