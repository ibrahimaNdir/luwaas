<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaiementAdminRessource;
use App\Http\Resources\PaiementLocataireRessource;
use App\Http\Resources\PaiementProprietaireRessource;
use App\Models\Bail;
use App\Models\Paiement;
use App\Services\Proprietaire\PaiementService;
use Illuminate\Http\Request;

class PaiementController extends Controller
{
    protected $paiementService;

    public function __construct(PaiementService $paiementService)
    {
        $this->paiementService= $paiementService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $offres =  $this->paiementService->index();
        return PaiementAdminRessource::collection($offres);
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

    public function paiementsForBailleur(Request $request)
    {
        $proprioId = $request->user()->id;

        $paiements = Paiement::whereHas('bail.logement.propriete', function ($query) use ($proprioId) {
            $query->where('proprietaire_id', $proprioId); // champ de la table propriétés
        })->with('bail', 'bail.logement', 'bail.logement.propriete', 'locataire')->get();

        return response()->json($paiements);
    }


    public function indexByBail($bailId)
    {
        $user = auth()->user();
        $locataireId = $user->locataire->id;

        // Vérifie que le bail appartient au locataire connecté
        $bail = Bail::where('id', $bailId)
            ->where('locataire_id', $locataireId)
            ->first();

        if (!$bail) {
            return response()->json([
                'message' => 'Accès refusé : ce bail ne vous appartient pas.'
            ], 403);
        }

        // Récupère tous les paiements liés à ce bail
        $paiements = Paiement::where('bail_id', $bailId)->get();
        return PaiementLocataireRessource::collection($paiements);
    }







}
