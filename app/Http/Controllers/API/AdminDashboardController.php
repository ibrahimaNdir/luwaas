<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Proprietaire;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * GET /api/admin/bailleurs/ratio-paiements
     * 
     * Retourne la liste des bailleurs avec le nombre total de paiements reçus,
     * le nombre en ligne, manuels, et le ratio manuel/en_ligne.
     */
    public function ratioPaiements(Request $request)
    {
        $proprietaires = Proprietaire::with(['user'])->get();

        $data = $proprietaires->map(function ($proprietaire) {
            // Paiements liés aux propriétés de ce propriétaire
            $paiements = \App\Models\Paiement::whereHas('bail.logement.propriete', function ($query) use ($proprietaire) {
                $query->where('proprietaire_id', $proprietaire->id);
            })->where('statut', 'payé')->get();

            $totalPaiements = $paiements->count();
            
            $paiementsManuels = $paiements->filter(function ($paiement) {
                return $paiement->transactions()->whereIn('mode_paiement', ['especes', 'wave_direct'])->exists();
            })->count();

            $paiementsEnLigne = $totalPaiements - $paiementsManuels;

            $ratio = $paiementsEnLigne > 0 
                ? round(($paiementsManuels / $paiementsEnLigne) * 100, 1) 
                : ($paiementsManuels > 0 ? 100 : 0);

            return [
                'proprietaire_id'   => $proprietaire->id,
                'nom_complet'       => ($proprietaire->user->prenom ?? '') . ' ' . ($proprietaire->user->nom ?? ''),
                'email'             => $proprietaire->user->email ?? '',
                'telephone'         => $proprietaire->user->telephone ?? '',
                'total_paiements'   => $totalPaiements,
                'paiements_manuels' => $paiementsManuels,
                'paiements_en_ligne'=> $paiementsEnLigne,
                'ratio_manuel'      => $ratio . '%'
            ];
        })->sortByDesc('paiements_manuels')->values();

        // Pagination
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);
        $paginatedData = new \Illuminate\Pagination\LengthAwarePaginator(
            $data->forPage($page, $perPage),
            $data->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'success' => true,
            'message' => 'Ratio des paiements manuels par bailleur',
            'data'    => $paginatedData
        ]);
    }
}
