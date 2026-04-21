<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proprietaire;
use Illuminate\Http\Request;

class AdminProprietaireController extends Controller
{
    /**
     * Liste tous les propriétaires avec pagination
     */
    public function index(Request $request)
    {
        $proprietaires = Proprietaire::with(['user', 'activeSubscription'])
            ->when($request->search, function ($query, $search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                      ->orWhere('prenom', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->statut, function ($query, $statut) {
                // filtrer par subscription_status : active, trial, expired, cancelled
                $query->where('subscription_status', $statut);
            })
            ->when($request->is_actif !== null, function ($query) use ($request) {
                $query->where('is_actif', filter_var($request->is_actif, FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data'    => $proprietaires
        ]);
    }

    /**
     * Détail d'un propriétaire
     */
    public function show(string $id)
    {
        $proprietaire = Proprietaire::with([
            'user',
            'proprietes',
            'subscriptions',
            'activeSubscription'
        ])->findOrFail($id);

        // Stats rapides du propriétaire
        $stats = [
            'total_proprietes'  => $proprietaire->proprietes()->count(),
            'total_logements'   => $proprietaire->proprietes()
                                    ->withCount('logements')
                                    ->get()
                                    ->sum('logements_count'),
        ];

        return response()->json([
            'success' => true,
            'data'    => $proprietaire,
            'stats'   => $stats
        ]);
    }

    /**
     * Activer un propriétaire
     */
    public function activate(string $id)
    {
        $proprietaire = Proprietaire::findOrFail($id);
        $proprietaire->update(['is_actif' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Propriétaire activé avec succès.',
            'data'    => $proprietaire
        ]);
    }

    /**
     * Suspendre un propriétaire
     */
    public function suspend(string $id)
    {
        $proprietaire = Proprietaire::findOrFail($id);
        $proprietaire->update(['is_actif' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Propriétaire suspendu avec succès.',
            'data'    => $proprietaire
        ]);
    }
}