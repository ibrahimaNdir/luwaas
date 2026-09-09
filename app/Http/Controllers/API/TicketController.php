<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

/**
 * Controller pour la gestion des tickets par les utilisateurs
 * (locataires ET propriétaires — accessible via routes communes auth:sanctum)
 */
class TicketController extends Controller
{
    /**
     * Lister les tickets de l'utilisateur connecté
     */
    public function index(Request $request)
    {
        $tickets = Ticket::where('user_id', $request->user()->id)
            ->when($request->statut, fn($q, $s) => $q->where('statut', $s))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'data'    => $tickets,
        ]);
    }

    /**
     * Créer un nouveau ticket
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sujet'   => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        $ticket = Ticket::create([
            'user_id' => $request->user()->id,
            'sujet'   => $validated['sujet'],
            'message' => $validated['message'],
            'statut'  => 'ouvert',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket créé avec succès.',
            'data'    => $ticket,
        ], 201);
    }

    /**
     * Voir un ticket (uniquement le sien)
     */
    public function show(Request $request, string $id)
    {
        $ticket = Ticket::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $ticket,
        ]);
    }
}
