<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GracePeriodStartedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        //
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Votre abonnement Luwaas a expiré - Sursis de 3 jours')
                    ->greeting('Bonjour ' . $notifiable->name . ',')
                    ->line('Votre abonnement Luwaas est arrivé à expiration et n\'a pas pu être renouvelé automatiquement.')
                    ->line('Vous disposez d\'un délai de grâce de 3 jours pour régulariser votre paiement.')
                    ->line('Passé ce délai, vos annonces seront bloquées et vous ne pourrez plus publier de nouveaux logements.')
                    ->action('Renouveler mon abonnement', url('/abonnements'))
                    ->line('Notez que les paiements de vos locataires existants continueront d\'être reversés sans interruption.');
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Abonnement expiré',
            'message' => 'Vous disposez d\'un délai de grâce de 3 jours pour régulariser votre paiement.',
            'action_url' => '/abonnements',
        ];
    }
}
