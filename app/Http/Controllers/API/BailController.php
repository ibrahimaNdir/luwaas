<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\BailAdminRessource;
use App\Http\Resources\BailLocataireResource;
use App\Http\Resources\BailProprietaireRessource;
use App\Http\Resources\BauxLocataireRessource;
use App\Models\Bail;
use App\Models\Logement;
use App\Models\Paiement;
use App\Services\Proprietaire\BailService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
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
        return BailAdminRessource::collection($offres);
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

        // MAJ du statut du logement en "occupé"
        $logement = Logement::find($validated['logement_id']);
        if ($logement) {
            $logement->statut_occupe = 'occupe'; // ou 1 si tu utilises un entier/boolean
            $logement->save();
        }

        // Génération automatique des paiements liés au bail
        $start = Carbon::parse($validated['date_debut']);
        $end   = Carbon::parse($validated['date_fin']);
        $current = $start->copy();

        while ($current <= $end) {
            $jour = min($validated['jour_echeance'], $current->copy()->endOfMonth()->day);
            $dateEcheance = $current->copy()->day($jour);
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
            'message' => 'Bail créé avec succès, statut logement mis à jour, paiements générés !',
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
        $logement = $this->bailService->show($id);
        if (!$logement) {
            return response()->json(['message' => 'Logement non trouvé'], 404);
        }
        return new  BailLocataireResource($logement );
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


    public function bauxForLocataire(Request $request)
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


        return  BauxLocataireRessource::collection($baux);
    }

    public function paiementsBail(Request $request, $bailId)
    {
        $user = $request->user();
        $bail = Bail::where('id', $bailId)
            ->where('locataire_id', $user->id) // sécurité : bail appartient au locataire
            ->firstOrFail();

        $paiements = $bail->paiements; // liste de tous les paiements liés à ce bail

        return response()->json($paiements);
    }

    public function exportPdf($bailId)
    {
        $bail = Bail::findOrFail($bailId); // récupère le bail
        $pdf = PDF::loadView('bail_pdf', compact('bail'));
        return $pdf->download('bail-'.$bail->id.'.pdf');
    }

    public function ResilierBail($bailId)
    {

    }







}
