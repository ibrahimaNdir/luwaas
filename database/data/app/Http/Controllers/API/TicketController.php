<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    /**
     * Liste des tickets de l'utilisateur connecté
     */
    public function index(Request $request)
    {
        $tickets = Ticket::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $tickets]);
    }

    /**
     * Créer un ticket de support
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sujet'   => 'required|string|max:255',
            'message' => 'required|string|max:3000',
        ]);

        $ticket = Ticket::create([
            'user_id' => $request->user()->id,
            'sujet'   => $validated['sujet'],
            'message' => $validated['message'],
            'statut'  => 'ouvert',
        ]);

        // 🔔 Notifier l'admin via event
        event(new \App\Events\NouveauTicketSoumis($ticket));

        return response()->json([
            'success' => true,
            'message' => 'Ticket de support créé avec succès. L\'administration a été notifiée.',
            'data'    => $ticket,
        ], 201);
    }

    /**
     * Voir un ticket en détail
     */
    public function show(string $id, Request $request)
    {
        $ticket = Ticket::where('user_id', $request->user()->id)->findOrFail($id);

        return response()->json(['success' => true, 'data' => $ticket]);
    }
}
