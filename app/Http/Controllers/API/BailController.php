<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\BailLocataireResource;
use App\Http\Resources\BailProprietaireRessource;
use App\Models\Bail;
use App\Models\Paiement;
use App\Services\Proprietaire\BailService;
use Illuminate\Http\Request;

class BailController extends Controller
{

    public function __construct()
    {
        $this->bailService = new BailService();
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $offres =  $this->bailService->index();
        return response()->json($offres,200);
        //
    }
    // Création d’un bail côté bailleur
    public function store(Request $request)
    {
        $validated = $request->validate([
            'logement_id' => 'required|exists:logements,id',
            'locataire_id' => 'required|exists:locataires,id',
            'charges_mensuelles' => 'required|integer|min:0',
            'caution' => 'required|integer|min:0',
            'montant_loyer' => 'required|integer|min:0',
            'cautions_a_payer' => 'required|integer|min:0',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'jour_echeance' => 'required|integer|min:1|max:31',
            'renouvellement_automatique' => 'required|boolean',
        ]);

        // Création du bail
        $bail = Bail::create($validated);

        // Génération automatique des paiements liés au bail
        $start = \Carbon\Carbon::parse($validated['date_debut']);
        $end   = \Carbon\Carbon::parse($validated['date_fin']);
        $current = $start->copy();
        while ($current <= $end) {
            // Gérer le jour d’échéance qui existe selon le mois
            $jour = min($validated['jour_echeance'], $current->copy()->endOfMonth()->day);
            $dateEcheance = $current->copy()->day($jour);

            // Format de la période "Novembre 2025"
            $periode = $current->isoFormat('MMMM YYYY');

            Paiement::create([
                'locataire_id'    => $bail->locataire_id,
                'bail_id'         => $bail->id,
                'montant_attendu' => $bail->montant_loyer,
                'statut'          => 'impayé',
                'date_echeance'   => $dateEcheance,
                'periode'         => $periode,
            ]);
            $current->addMonth();
        }

        return response()->json([
            'success' => true,
            'message' => 'Bail créé avec succès et paiements générés !',
            'bail' => $bail
        ], 201);
    }


    // Voir tous les baux pour le bailleur connecté
    public function bauxBailleur(Request $request)
    {
        $user = $request->user();
        $proprietaire_id = $user->proprietaire->id ?? null;
        if (!$proprietaire_id) {
            return response()->json(['message' => 'Non autorisé ou pas de profil bailleur.'], 403);
        }

        // Affiche tous les baux où le logement appartient au bailleur courant
        $baux = Bail::with(['locataire', 'logement'])
            ->whereHas('logement.propriete', function($q) use ($proprietaire_id) {
                $q->where('proprietaire_id', $proprietaire_id);
            })
            ->orderByDesc('date_debut')
            ->get();

        return BailProprietaireRessource::collection($baux);
    }

    // Voir les baux d’un locataire connecté
    public function bauxLocataire(Request $request)
    {
        $user = $request->user();
        $locataire_id = $user->locataire->id ?? null;
        if (!$locataire_id) {
            return response()->json(['message' => 'Non autorisé ou pas de profil locataire.'], 403);
        }

        $baux = Bail::with(['logement'])
            ->where('locataire_id', $locataire_id)
            ->orderByDesc('date_debut')
            ->get();


        return BailLocataireResource::collection($baux);
    }

    // Voir le détail d’un bail
    public function show($id)
    {
        $bail = Bail::with(['logement', 'locataire'])->findOrFail($id);

        return response()->json($bail);
    }

    public function destroy($id)
    {
        $bail = Bail::findOrFail($id);

        // Suppression du bail ; les paiements liés (avec bail_id) seront supprimés grâce au "cascade"
        $bail->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bail et paiements associés supprimés avec succès.'
        ]);
    }

}
