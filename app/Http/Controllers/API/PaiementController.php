<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaiementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    // app/Http/Controllers/PaiementController.php

    public function paiementARegler(Request $request, $bailId)
    {
        $user = $request->user(); // locataire connecté

        // Cherche le 1er paiement non réglé pour ce bail et ce locataire (mois en retard ou impayé)
        $paiement = \App\Models\Paiement::where('bail_id', $bailId)
            ->where('locataire_id', $user->id)
            ->whereIn('statut', ['impayé', 'en_retard'])
            ->orderBy('date_echeance', 'asc')
            ->first();

        if (!$paiement) {
            return response()->json([
                'message' => 'Tous les paiements sont réglés pour ce bail !'
            ], 200);
        }

        // On renvoie FA uniquement le paiement à régler avec détail du bail
        return response()->json([
            'paiement_id' => $paiement->id,
            'montant_a_payer' => $paiement->montant_attendu,
            'periode' => $paiement->periode,
            'date_echeance' => $paiement->date_echeance,
            'bail' => $paiement->bail, // relation du bail (peut inclure infos logement, bailleur, etc.)
        ], 200);
    }

    public function bauxAvecStatutPaiement(Request $request)
    {
        $user = $request->user(); // locataire connecté

        // Récupère tous les baux du locataire
        $baux = \App\Models\Bail::where('locataire_id', $user->id)->with('logement')->get();

        // Map chaque bail avec le paiement à régler
        $data = $baux->map(function ($bail) {
            // On cherche le premier paiement non réglé pour ce bail
            $paiement = $bail->paiements()
                ->whereIn('statut', ['impayé', 'en_retard'])
                ->orderBy('date_echeance', 'asc')
                ->first();

            return [
                'bail_id'         => $bail->id,
                'logement'        => $bail->logement->numero,
                'montant_loyer'   => $bail->prix_loyer,
                'periode_en_cours'=> $paiement->periode ?? null,
                'statut_paiement' => $paiement->statut ?? 'payé',
                'date_echeance'   => $paiement->date_echeance ?? null,
            ];
        });

        return response()->json($data);
    }


}
