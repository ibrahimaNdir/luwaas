<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBailRequest;
use App\Http\Resources\BailLocataireResource;
use App\Http\Resources\BailProprietaireRessource;
use App\Models\Bail;
use App\Models\Demande;
use App\Services\BailService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BailController extends Controller
{
    public function __construct(protected BailService $bailService) {}

    // ═══════════════════════════════════════════
    // 1. CRÉATION (Côté Bailleur)
    // ═══════════════════════════════════════════

    public function store(StoreBailRequest $request)
    {
        $proprietaireId = $request->user()->proprietaire->id ?? null;

        if (!$proprietaireId) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $demande = Demande::with('logement.propriete')->findOrFail($request->demande_id);

        $erreur = $this->bailService->validerDemande($demande, $proprietaireId);
        if ($erreur) {
            return response()->json(['message' => $erreur], 422);
        }

        $bail = $this->bailService->creerBail($demande, $request->validated());

        $montantTotalSignature = $bail->montant_caution_total + $bail->montant_loyer;
        $periode = Carbon::parse($bail->date_debut)->isoFormat('MMMM YYYY');

        return response()->json([
            'success' => true,
            'message' => 'Bail créé avec succès. Le locataire a été notifié.',
            'bail' => [
                'id'                      => $bail->id,
                'statut'                  => $bail->statut,
                'demande_id'              => $demande->id,
                'montant_total_signature' => $montantTotalSignature,
                'periode_couverte'        => $periode,
                'details' => [
                    'caution'       => $bail->montant_caution_total,
                    'premier_loyer' => $bail->montant_loyer,
                ],
                'date_limite_paiement' => now()->addDays(7)->format('Y-m-d'),
            ]
        ], 201);
    }

    // ═══════════════════════════════════════════
    // 2. BAIL EN ATTENTE (Côté Locataire)
    // ═══════════════════════════════════════════

    public function getBailEnAttente(Request $request)
    {
        $locataireId = $request->user()->locataire->id ?? null;

        if (!$locataireId) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $bail = Bail::with(['logement.propriete.proprietaire.user', 'paiements'])
            ->where('locataire_id', $locataireId)
            ->where('statut', 'en_attente_paiement')
            ->first();

        if (!$bail) {
            return response()->json(['message' => 'Aucun bail en attente de signature.'], 404);
        }

        return response()->json([
            'bail' => [
                'id'      => $bail->id,
                'logement' => [
                    'type'    => $bail->logement->typelogement ?? '',
                    'numero'  => $bail->logement->numero ?? '',
                    'adresse' => $bail->logement->propriete->adresse ?? '',
                ],
                'bailleur' => [
                    'nom'       => $bail->logement->propriete->proprietaire->user->nom ?? '',
                    'prenom'    => $bail->logement->propriete->proprietaire->user->prenom ?? '',
                    'telephone' => $bail->logement->propriete->proprietaire->user->telephone ?? '',
                ],
                'finances' => [
                    'loyer_mensuel' => $bail->montant_loyer,
                    'charges'       => $bail->charges_mensuelles,
                    'caution'       => $bail->montant_caution_total,
                    'total_a_payer' => $bail->montant_caution_total + $bail->montant_loyer,
                ],
                'dates' => [
                    'debut'      => $bail->date_debut,
                    'fin'        => $bail->date_fin,
                    'duree_mois' => Carbon::parse($bail->date_debut)->diffInMonths($bail->date_fin),
                ],
                'conditions_speciales' => $bail->conditions_speciales,
            ]
        ]);
    }

    // ═══════════════════════════════════════════
    // 3. CONSULTATION
    // ═══════════════════════════════════════════

    public function bauxBailleur(Request $request)
    {
        $proprietaireId = $request->user()->proprietaire->id ?? null;

        if (!$proprietaireId) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $baux = Bail::with(['locataire.user', 'logement'])
            ->whereHas('logement.propriete', fn($q) => $q->where('proprietaire_id', $proprietaireId))
            ->orderByDesc('created_at')
            ->get();

        return BailProprietaireRessource::collection($baux);
    }

    public function bauxLocataire(Request $request)
    {
        $locataireId = $request->user()->locataire->id ?? null;

        if (!$locataireId) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $baux = Bail::with(['logement.propriete'])
            ->where('locataire_id', $locataireId)
            ->orderByDesc('created_at')
            ->get();

        return BailLocataireResource::collection($baux);
    }

    public function show($id)
    {
        $bail = Bail::with(['locataire.user', 'logement.propriete.proprietaire.user', 'paiements'])
            ->findOrFail($id);

        if (!$this->bailService->verifierAcces($bail, auth()->id())) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        return new BailLocataireResource($bail);
    }

    // ═══════════════════════════════════════════
    // 4. EXPORT PDF
    // ═══════════════════════════════════════════

    public function exportPdf($bailId)
    {
        $bail = Bail::with(['locataire.user', 'logement.propriete.proprietaire.user'])
            ->findOrFail($bailId);

        if (!$this->bailService->verifierAcces($bail, auth()->id())) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        if ($bail->statut !== 'actif') {
            return response()->json(['message' => 'Le bail doit être signé pour télécharger le PDF.'], 422);
        }

        return PDF::loadView('bail_pdf', compact('bail'))
            ->download('Contrat_Location_' . $bail->id . '.pdf');
    }

    // ═══════════════════════════════════════════
    // 5. SUPPRESSION
    // ═══════════════════════════════════════════

    public function destroy($id)
    {
        $bail = Bail::findOrFail($id);

        if ($bail->statut === 'actif') {
            return response()->json(['message' => 'Impossible de supprimer un bail actif.'], 422);
        }

        $bail->delete();

        return response()->json(['success' => true, 'message' => 'Bail supprimé avec succès.']);
    }
}