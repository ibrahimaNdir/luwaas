<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

/**
 * Controller admin pour la gestion des tickets de support
 */
class AdminTicketController extends Controller
{
    /**
     * Lister tous les tickets avec filtre optionnel par statut
     */
    public function index(Request $request)
    {
        $tickets = Ticket::with('user:id,name,email,phone,user_type')
            ->when($request->statut, fn($q, $s) => $q->where('statut', $s))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data'    => $tickets,
        ]);
    }

    /**
     * Voir un ticket en détail
     */
    public function show(string $id)
    {
        $ticket = Ticket::with('user:id,name,email,phone,user_type')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $ticket,
        ]);
    }

    /**
     * Répondre à un ticket (+ passer en "en_cours" si encore ouvert)
     */
    public function repondre(Request $request, string $id)
    {
        $validated = $request->validate([
            'reponse_admin' => 'required|string|max:10000',
        ]);

        $ticket = Ticket::findOrFail($id);

        $ticket->update([
            'reponse_admin' => $validated['reponse_admin'],
            'statut'        => $ticket->statut === 'ouvert' ? 'en_cours' : $ticket->statut,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Réponse envoyée.',
            'data'    => $ticket->fresh(),
        ]);
    }

    /**
     * Fermer un ticket
     */
    public function fermer(string $id)
    {
        $ticket = Ticket::findOrFail($id);

        if ($ticket->statut === 'ferme') {
            return response()->json([
                'success' => false,
                'message' => 'Ce ticket est déjà fermé.',
            ], 422);
        }

        $ticket->update([
            'statut'    => 'ferme',
            'closed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket fermé.',
            'data'    => $ticket->fresh(),
        ]);
    }

    /**
     * Résumé : comptage par statut
     */
    public function stats()
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'ouverts'   => Ticket::where('statut', 'ouvert')->count(),
                'en_cours'  => Ticket::where('statut', 'en_cours')->count(),
                'fermes'    => Ticket::where('statut', 'ferme')->count(),
                'total'     => Ticket::count(),
            ],
        ]);
    }
}
