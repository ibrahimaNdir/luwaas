<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaiementAdminRessource;
use App\Http\Resources\PaiementDetailsRessources;
use App\Http\Resources\PaiementLocataireRessource;
use App\Http\Resources\ProprieteResource;
use App\Models\Bail;
use App\Models\Paiement;
use App\Models\Transaction;
use App\Notifications\PaiementEspecesDemande;
use App\Services\FirebaseNotificationService;
use App\Services\Proprietaire\PaiementService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class PaiementController extends Controller
{
    protected $paiementService;

    public function __construct(PaiementService $paiementService)
    {
        $this->paiementService = $paiementService;
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
            $query->where('proprietaire_id', $proprioId);
        })
        ->with('bail', 'bail.logement.propriete', 'locataire.user')
        ->orderByDesc('date_paiement')
        ->get();

    return ProprieteResource::collection($paiements);
}


    // Methode qui liste tous les Paiements lier a un Bail ( cote Locataire)
    public function indexByPaiement($bailId)
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

    public function detailPaiement($bailId, $id)
    {
        $paiement = Paiement::where('id', $id)
            ->where('bail_id', $bailId)
            ->first();

        if (!$paiement) {
            return response()->json(['message' => 'Paiement non trouvé pour ce bail'], 404);
        }
        return new PaiementDetailsRessources($paiement);
    }


    // Methode pour payer en espece un Paiement lier a un Bail ( cote Locataire)

    public function payerEspeces(Request $request, $bailId, $paiement_id, NotificationService $notifService)
    {
        $paiement = Paiement::where('id', $paiement_id)
            ->where('bail_id', $bailId)
            ->firstOrFail();

        $user = auth()->user();
        $locataireId = $user->locataire->id;

        // 1. Vérification sécurité
        if ($paiement->bail->locataire_id !== $locataireId) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        if ($paiement->statut === "payé") {
            return response()->json(['message' => 'Ce paiement est déjà réglé'], 400);
        }

        // 2. Vérifier si une demande existe déjà
        if (Transaction::where('paiement_id', $paiement->id)
            ->where('statut', 'en_attente')->exists()
        ) {
            return response()->json(['message' => 'Une demande de paiement espèces existe déjà pour ce mois.'], 400);
        }

        // 3. Créer la transaction "En attente"
        $transaction = Transaction::create([
            'paiement_id'      => $paiement->id,
            'mode_paiement'    => 'especes',
            'montant'          => $paiement->montant_attendu,
            'statut'           => 'en_attente',
            'date_transaction' => now(),
        ]);

        // 🔥 4. Notification au BAILLEUR (Propriétaire)
        // "Le locataire X veut payer en espèces, validez-le !"
        $bailleur = $paiement->bail->proprietaire->user ?? null; // Récupère le modèle Bailleur

        // On s'assure que le bailleur a un compte User associé pour la notif
        if ($bailleur && $bailleur->user) {
            $nomCompletLocataire = $user->prenom . ' ' . $user->nom;

            $notifService->sendToUser(
                $bailleur->user, // L'utilisateur cible (User du bailleur)
                "Paiement Espèces 💵",
                "{$nomCompletLocataire} souhaite payer son loyer en espèces. Validez la réception.",
                "validation_especes" // Type pour redirection éventuelle vers écran de validation
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Demande de paiement en espèces enregistrée et notifiée au bailleur.',
            'transaction' => $transaction
        ]);
    }



    // Methode pour que le Bailleur valide le paiement en espece d'une Transaction lier a un Paiement
    public function validerEspeces(Request $request, $transaction_id, NotificationService $notifService)
    {
        $transaction = Transaction::findOrFail($transaction_id);

        // 1. Vérifie que l'utilisateur actuel est bien le bailleur
        $user = auth()->user();
        $paiement = $transaction->paiement;

        if (
            !$paiement ||
            !$paiement->bail ||
            !$paiement->bail->logement ||
            !$paiement->bail->logement->propriete ||
            !$paiement->bail->logement->propriete->proprietaire
        ) {
            return response()->json(['message' => 'Données de paiement invalides.'], 400);
        }

        $proprietaire = $paiement->bail->logement->propriete->proprietaire;

        if ($proprietaire->user_id !== $user->id) {
            return response()->json(['message' => 'Seul le bailleur associé peut valider ce paiement.'], 403);
        }

        // 2. Validation : update transaction et paiement
        $transaction->update([
            'statut' => 'valide',
            'date_validation' => now(),
            'valide_par' => $user->id,
        ]);

        $paiement->update([
            'statut' => 'payé',
            'date_paiement' => now(),
        ]);

        // 🔥 3. Notification au LOCATAIRE (celui qui a payé)
        $locataire = $paiement->locataire;

        // On vérifie que le locataire a un User associé pour la notif
        if ($locataire && $locataire->user) {
            $logementInfo = $paiement->bail->logement->numero ?? 'Inconnu';
            $periodeInfo = $paiement->periode ?? '';

            $notifService->sendToUser(
                $locataire->user,
                "Paiement Validé ✅",
                "Votre paiement espèces pour {$logementInfo} ($periodeInfo) a été validé par le bailleur.",
                "paiement_valide" // Type pour rediriger vers l'écran d'historique
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Le paiement en espèces a été validé et le locataire notifié.',
        ]);
    }
}
