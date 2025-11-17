<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaiementAdminRessource;
use App\Http\Resources\PaiementDetailsRessources;
use App\Http\Resources\PaiementLocataireRessource;
use App\Http\Resources\PaiementProprietaireRessource;
use App\Models\Bail;
use App\Models\Paiement;
use App\Models\Transaction;
use App\Notifications\PaiementEspecesDemande;
use App\Services\FirebaseNotificationService;
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


/*
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
    } */




  //
    public function paiementsForBailleur(Request $request)
    {
        $proprioId = $request->user()->id;

        $paiements = Paiement::whereHas('bail.logement.propriete', function ($query) use ($proprioId) {
            $query->where('proprietaire_id', $proprioId); // champ de la table propriétés
        })->with('bail', 'bail.logement', 'bail.logement.propriete', 'locataire')->get();

        return response()->json($paiements);
    }

 // Methode qui liste tous les Paiements lier a un Bail ( cote Locataire)
    public function indexByPaiement ($bailId)
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

    public function detailPaiement($bailId, $id) {
        $paiement = Paiement::where('id', $id)
            ->where('bail_id', $bailId)
            ->first();

        if (!$paiement) {
            return response()->json(['message' => 'Paiement non trouvé pour ce bail'], 404);
        }
        return new PaiementDetailsRessources($paiement);
    }


    public function payerEspeces(Request $request, $paiement_id)
    {
        $paiement = Paiement::findOrFail($paiement_id);

        if ($paiement->locataire_id !== auth()->id()) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        if ($paiement->statut === "payé") {
            return response()->json(['message' => 'Ce paiement est déjà réglé'], 400);
        }

        if (\App\Models\Transaction::where('paiement_id', $paiement->id)
            ->where('statut', 'en_attente')->exists()) {
            return response()->json(['message' => 'Une demande de paiement espèces existe déjà pour ce mois.'], 400);
        }


        $transaction = \App\Models\Transaction::create([
            'paiement_id'      => $paiement->id,
            'mode_paiement'    => 'especes',
            'montant'          => $paiement->montant_attendu,
            'statut'           => 'en_attente',
            'date_transaction' => now(),
        ]);

        // Notification push au bailleur (si dispo)
        $bailleur = $paiement->bail->bailleur ?? null;
        if ($bailleur && $bailleur->firebase_token) {
            \App\Services\FirebaseNotificationService::send(
                $bailleur->firebase_token,
                'Paiement espèces à valider',
                'Le locataire '.$paiement->locataire->user->prenom.' ' .$paiement->locataire->user->nom . ' souhaite payer en espèces.',
                ['type' => 'paiement_especes', 'transaction_id' => $transaction->id]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Demande de paiement en espèces enregistrée.',
            'transaction' => $transaction
        ]);
    }
    public function validerEspeces(Request $request, $transaction_id)
    {
        $transaction = \App\Models\Transaction::findOrFail($transaction_id);

        // Vérifie que l'utilisateur actuel est bien le bailleur du logement
        $user = auth()->user();
        $paiement = $transaction->paiement;

        if (!$paiement || !$paiement->bail || $paiement->bail->logement->propriete->proprietaire_id !== $user->id) {
            return response()->json(['message' => 'Seul le bailleur associé peut valider ce paiement.'], 403);
        }

        if ($transaction->statut !== 'en_attente') {
            return response()->json(['message' => 'Transaction déjà validée ou refusée.'], 400);
        }

        // Validation : on passe la transaction à "valide", on marque le paiement comme "payé"
        $transaction->update([
            'statut' => 'valide',
            'date_validation' => now(),
            'valide_par' => $user->id,
        ]);

        $paiement->update([
            'statut' => 'payé',
            'date_paiement' => now(),
        ]);
        $locataire = $paiement->locataire;
        if ($locataire && $locataire->firebase_token) {
            \App\Services\FirebaseNotificationService::send(
                $locataire->firebase_token,
                'Paiement espèces validé',
                'Votre paiement pour le logement ' . ($paiement->bail->logement->numero ?? '') .
                ' - Periode  ' . $paiement->periode . ' '. ' a été validé par le bailleur.',
                [
                    'type' => 'paiement_especes_valide',
                    'transaction_id' => $transaction->id,
                    'paiement_id' => $paiement->id
                ]
            );
        }

        // Optionnel : notification au locataire (push, email...)

        return response()->json([
            'success' => true,
            'message' => 'Le paiement en espèces a été validé.',
        ]);
    }



}
