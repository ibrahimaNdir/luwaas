<?php

namespace App\Listeners;

use App\Events\NouveauTicketSoumis;
use App\Models\Admin;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class NotifierAdminNouveauTicket
{
    public function __construct(protected NotificationService $notificationService) {}

    public function handle(NouveauTicketSoumis $event): void
    {
        $ticket = $event->ticket;
        $user   = $ticket->user;

        $admins = Admin::with('user')->get();

        foreach ($admins as $admin) {
            if (!$admin->user) continue;

            $this->notificationService->sendToUser(
                $admin->user,
                '🎫 Nouveau ticket de support',
                "{$user?->prenom} {$user?->nom} : {$ticket->sujet}",
                'admin_nouveau_ticket',
                ['ticket_id' => $ticket->id]
            );
        }

        Log::info("🔔 Admins notifiés : nouveau ticket #{$ticket->id}");
    }
}
