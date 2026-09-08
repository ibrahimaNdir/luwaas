<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiredNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param string $type The type of notification ('pro_to_starter', 'pro_expiring_soon', etc.)
     * @param string|null $newPlan The new plan after change (if applicable)
     * @param array $additionalData Additional data for the notification
     */
    public function __construct(
        public string $type,
        public ?string $newPlan = null,
        public array $additionalData = []
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = new MailMessage();
        $user = $notifiable;

        match ($this->type) {
            'pro_to_starter' => {
                $message->subject('Votre abonnement Pro a expiré')
                    ->line('Bonjour ' . $user->prenom . ',')
                    ->line('Nous vous informons que votre abonnement Pro est arrivé à expiration.')
                    ->line('Vous bénéficiez d’un délai de grâce de **3 jours** pour décider lesquels de vos logements publiés (vacants) vous souhaitez garder en ligne.')
                    ->line('Passé ce délai, les logements excédentaires seront automatiquement archivés (statut « archivé »).')
                    ->line('Pendant le délai de grâce, vous pouvez toujours gérer vos propriétés et accepter de nouvelles demandes.')
                    ->line('')
                    ->line('Après les 3 jours, si vous n’avez pas fait de choix, le système conservera automatiquement les 5 logements vacants les plus récemment mis à jour.')
                    ->line('')
                    ->action('Gérer mon abonnement', url('/plans'))
                    ->line('Merci de votre compréhension.')
                    ->line('L\'équipe Luwaas');
            }
            'pro_expiring_soon' => {
                $daysLeft = $this->additionalData['days_left'] ?? 3;
                $message->subject('Votre abonnement Pro expire dans ' . $daysLeft . ' jour' . ($daysLeft > 1 : 's' : ''))
                    ->line('Bonjour ' . $user->prenom . ',')
                    ->line('C\'est une rappel amical : votre abonnement Pro expire dans ' . $daysLeft . ' jour' . ($daysLeft > 1 : 's' : '') . '.')
                    ->line('Pour éviter un retour automatique au plan Starter gratuit et continuer à profiter de toutes les fonctionnalités Pro,')
                    ->line('veuillez renouveler votre abonnement avant la date d\'expiration.')
                    ->line('')
                    ->line('Avec le plan Pro, vous bénéficiez de :')
                    ->line('• Gestion de jusqu\'à 15 propriétés')
                    ->line('• Mise en avant des logements')
                    ->line('• Rapports financiers avancés')
                    ->line('• Export Excel des données')
                    ->line('')
                    ->action('Renouveler maintenant', url('/plans'))
                    ->line('Merci de votre fidélité.')
                    ->line('L\'équipe Luwaas');
            }
            default => {
                $message->subject('Notification d\'abonnement')
                    ->line('Bonjour ' . $user->prenom . ',')
                    ->line('Vous avez reçu une notification concernant votre abonnement.')
                    ->line('Type : ' . ucfirst($this->type))
                    ->when($this->newPlan, function ($message) {
                        return $message->line('Nouveau plan : ' . ucfirst($this->newPlan));
                    })
                    ->action('Gérer mon abonnement', url('/plans'))
                    ->line('Thank you for using our application!');
            }
        };

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'new_plan' => $this->newPlan,
            'data' => $this->additionalData,
            'timestamp' => now()->toISOString(),
        ];
    }
}