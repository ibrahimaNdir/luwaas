<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\DemandeLocataireResource;
use App\Http\Resources\DemandeProprietaireResource;
use App\Models\Demande;
use App\Models\Logement;
use App\Services\DemandeService;
use Illuminate\Http\Request;

class DemandeController extends Controller
{
    public function __construct(protected DemandeService $demandeService) {}

    // ═══════════════════════════════════════════
    // CÔTÉ LOCATAIRE
    // ═══════════════════════════════════════════

    public function store(Request $request)
    {
        $locataireId = $this->locataireId($request);

        $validated = $request->validate([
            'logement_id' => 'required|exists:logements,id',
        ]);

        $logement = Logement::with('propriete.proprietaire')->findOrFail($validated['logement_id']);

        if (!$logement->propriete?->proprietaire) {
            return response()->json(['message' => 'Impossible de trouver le propriétaire.'], 500);
        }

        $erreur = $this->demandeService->validerCreation($logement, $locataireId);
        if ($erreur) {
            return response()->json(['message' => $erreur['message']], $erreur['status']);
        }

        $demande = $this->demandeService->creer($logement, $locataireId);

        return response()->json([
            'success' => true,
            'message' => 'Demande envoyée au propriétaire.',
            'demande' => $demande,
        ], 201);
    }

    public function annuler(string $id, Request $request)
    {
        $locataireId = $this->locataireId($request);
        $demande     = Demande::findOrFail($id);

        if (!$this->demandeService->verifierLocataire($demande, $locataireId)) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        if (!in_array($demande->status, ['en_attente', 'acceptee'])) {
            return response()->json(['message' => 'Cette demande ne peut plus être annulée.'], 422);
        }

        $this->demandeService->annuler($demande);

        return response()->json(['success' => true, 'message' => 'Demande annulée avec succès.']);
    }

    public function destroy(string $id, Request $request)
    {
        $locataireId = $this->locataireId($request);
        $demande     = Demande::findOrFail($id);

        if (!$this->demandeService->verifierLocataire($demande, $locataireId)) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        if (!in_array($demande->status, ['refusee', 'annulee'])) {
            return response()->json([
                'message' => 'Impossible de supprimer une demande active. Annulez-la d\'abord.'
            ], 422);
        }

        $demande->delete();

        return response()->json(['success' => true, 'message' => 'Demande supprimée avec succès.']);
    }

    public function demandesLocataire(Request $request)
    {
        $locataireId = $this->locataireId($request);

        $demandes = Demande::with(['logement', 'proprietaire'])
            ->where('locataire_id', $locataireId)
            ->orderByDesc('date_demande')
            ->get();

        return DemandeLocataireResource::collection($demandes);
    }

    // ═══════════════════════════════════════════
    // CÔTÉ PROPRIÉTAIRE
    // ═══════════════════════════════════════════

    public function accepter($id, Request $request)
    {
        $proprietaireId = $this->proprietaireId($request);
        $demande        = Demande::findOrFail($id);

        if (!$this->demandeService->verifierProprietaire($demande, $proprietaireId)) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        if ($demande->status !== 'en_attente') {
            return response()->json(['message' => 'Cette demande ne peut plus être acceptée.'], 400);
        }

        $this->demandeService->accepter($demande);

        return response()->json([
            'success' => true,
            'message' => 'Demande acceptée. Le locataire a été notifié.',
            'demande' => $demande,
        ]);
    }

    public function refuser($id, Request $request)
    {
        $proprietaireId = $this->proprietaireId($request);
        $demande        = Demande::findOrFail($id);

        if (!$this->demandeService->verifierProprietaire($demande, $proprietaireId)) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        if ($demande->status !== 'en_attente') {
            return response()->json(['message' => 'Cette demande ne peut plus être refusée.'], 400);
        }

        $this->demandeService->refuser($demande);

        return response()->json(['success' => true, 'message' => 'Demande refusée.', 'demande' => $demande]);
    }

    public function demandesProprietaire(Request $request)
    {
        $proprietaireId = $this->proprietaireId($request);

        $demandes = Demande::with(['logement', 'locataire'])
            ->where('proprietaire_id', $proprietaireId)
            ->orderByDesc('date_demande')
            ->get();

        return DemandeProprietaireResource::collection($demandes);
    }

    // ═══════════════════════════════════════════
    // HELPERS PRIVÉS
    // ═══════════════════════════════════════════

    private function locataireId(Request $request): int
    {
        $id = $request->user()->locataire->id ?? null;
        abort_if(!$id, 403, 'Non autorisé.');
        return $id;
    }

    private function proprietaireId(Request $request): int
    {
        $id = $request->user()->proprietaire->id ?? null;
        abort_if(!$id, 403, 'Non autorisé.');
        return $id;
    }
}