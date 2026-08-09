<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class AdminTicketController extends Controller
{
    public function __construct(protected NotificationService $notificationService) {}

    /**
     * Liste tous les tickets de support
     */
    public function index(Request $request)
    {
        $tickets = Ticket::with('user')
            ->when($request->statut, fn($q, $statut) => $q->where('statut', $statut))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return response()->json(['success' => true, 'data' => $tickets]);
    }

    /**
     * Voir un ticket
     */
    public function show(string $id)
    {
        $ticket = Ticket::with('user')->findOrFail($id);

        return response()->json(['success' => true, 'data' => $ticket]);
    }

    /**
     * Répondre à un ticket de support
     */
    public function repondre(Request $request, string $id)
    {
        $request->validate([
            'reponse_admin' => 'required|string|max:3000',
        ]);

        $ticket = Ticket::with('user')->findOrFail($id);

        $ticket->update([
            'reponse_admin' => $request->reponse_admin,
            'statut'        => 'en_cours',
        ]);

        // 🔔 Notifier l'utilisateur que l'admin a répondu
        if ($ticket->user) {
            $this->notificationService->sendToUser(
                $ticket->user,
                '💬 Réponse à votre ticket de support',
                "L'administration a répondu à votre ticket : {$ticket->sujet}",
                'ticket_repondu',
                ['ticket_id' => $ticket->id]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Réponse envoyée avec succès au client.',
            'data'    => $ticket,
        ]);
    }

    /**
     * Clôturer un ticket
     */
    public function fermer(string $id)
    {
        $ticket = Ticket::findOrFail($id);

        $ticket->update([
            'statut'    => 'ferme',
            'closed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket clôturé avec succès.',
            'data'    => $ticket,
        ]);
    }
}
