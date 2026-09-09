<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProChoiceNeededNotification implements ShouldQueue
{
    use Queueable;

    public $logementIds;
    public $logements; // array of ['id'=>..., 'numero'=>..., 'adresse'=>...]

    /**
     * @param array $logementIds   IDs of the vacant published logements
     * @param array $logements     Array of associative arrays with id, numero, adresse
     */
    public function __construct(array $logementIds, array $logements)
    {
        $this->logementIds = $logementIds;
        $this->logements   = $logements;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $user = $notifiable;
        $message = new MailMessage();
        $message->subject('Choix des logements à conserver publiés')
            ->line('Bonjour ' . $user->prenom . ',')
            ->line('Votre délai de grâce de 3 jours est écoulé. Vous devez choisir lesquels de vos logements vacants publiés vous souhaitez garder en ligne (maximum 5).')
            ->line('Les logements non sélectionnés seront automatiquement archivés.')
            ->line('')
            ->line('Voici la liste des logements concernés :');

        foreach ($this->logements as $l) {
            $message->line("- {$l['numero']} – {$l['adresse']}");
        }

        $message->line('')
            ->action('Faire mon choix', url('/proprietaire/choix-logements'))
            ->line('Merci de votre compréhension.')
            ->line('L\'équipe Luwaas');

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'logement_ids' => $this->logementIds,
            'logements'    => $this->logements,
        ];
    }
}