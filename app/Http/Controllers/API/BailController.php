<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\BailLocataireResource;
use App\Http\Resources\BailProprietaireRessource;
use App\Models\Bail;
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
            'jour_echeance' => 'required|integer|min:1|max:10',
            'renouvellement_automatique' => 'required|boolean',
        ]);

        $bail = Bail::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bail créé avec succès.',
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
}
