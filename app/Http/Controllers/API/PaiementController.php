<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaiementLocataireRessource;
use App\Http\Resources\PaiementProprietaireRessource;
use App\Models\Bail;
use App\Models\Paiement;
use App\Services\PaiementService;
use Illuminate\Http\Request;

class PaiementController extends Controller
{
    public function __construct(protected PaiementService $paiementService) {}

    // ═══════════════════════════════════════════
    // CÔTÉ LOCATAIRE
    // ═══════════════════════════════════════════

    public function index(Request $request)
    {
        $locataireId = $this->locataireId($request);

        $paiements = Paiement::with(['bail.logement', 'transactions'])
            ->where('locataire_id', $locataireId)
            ->orderByDesc('date_echeance')
            ->get();

        return PaiementLocataireRessource::collection($paiements);
    }

    public function show($id, Request $request)
    {
        $locataireId = $this->locataireId($request);

        $paiement = Paiement::with(['bail.logement'])
            ->where('id', $id)
            ->whereHas('bail', fn($q) => $q->where('locataire_id', $locataireId))
            ->firstOrFail();

        $data = $paiement->statut === 'payé'
            ? $this->paiementService->formatPaiementPaye($paiement)
            : $this->paiementService->formatPaiementImpaye($paiement);

        return response()->json($data);
    }

    public function paiementsBail($bailId, Request $request)
    {
        $locataireId = $this->locataireId($request);

        Bail::where('id', $bailId)->where('locataire_id', $locataireId)->firstOrFail();

        $paiements = Paiement::with('transactions')
            ->where('bail_id', $bailId)
            ->orderBy('date_echeance')
            ->get();

        return PaiementLocataireRessource::collection($paiements);
    }

    public function paiementARegler($bailId, Request $request)
    {
        $locataireId = $this->locataireId($request);

        $bail = Bail::where('id', $bailId)->where('locataire_id', $locataireId)->firstOrFail();

        $paiement = $this->paiementService->prochainPaiementARegler($bailId);

        if (!$paiement) {
            return response()->json(['message' => 'Tous les paiements sont réglés !', 'tous_payes' => true]);
        }

        return response()->json([
            'tous_payes' => false,
            'paiement'   => [
                'id'              => $paiement->id,
                'type'            => $paiement->type,
                'montant_attendu' => $paiement->montant_attendu,
                'montant_restant' => $paiement->montant_restant,
                'periode'         => $paiement->periode,
                'date_echeance'   => $paiement->date_echeance,
                'statut'          => $paiement->statut,
            ],
            'bail' => [
                'id'      => $bail->id,
                'logement' => $bail->logement->numero ?? null,
            ],
        ]);
    }

    public function statistiques(Request $request)
    {
        $locataireId = $this->locataireId($request);

        return response()->json(
            $this->paiementService->statistiquesLocataire($locataireId)
        );
    }

    // ═══════════════════════════════════════════
    // CÔTÉ BAILLEUR
    // ═══════════════════════════════════════════

    public function paiementsProprietaire(Request $request)
    {
        $proprietaireId = $request->user()->proprietaire->id ?? null;

        if (!$proprietaireId) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $paiements = Paiement::with(['bail.logement', 'locataire.user', 'transactions'])
            ->whereHas('bail.logement.propriete', fn($q) => $q->where('proprietaire_id', $proprietaireId))
            ->when($request->statut, fn($q) => $q->where('statut', $request->statut))
            ->orderByDesc('date_echeance')
            ->paginate(20);

        return PaiementProprietaireRessource::collection($paiements);
    }

    // ═══════════════════════════════════════════
    // HELPER PRIVÉ
    // ═══════════════════════════════════════════

    private function locataireId(Request $request): int
    {
        $id = $request->user()->locataire->id ?? null;

        abort_if(!$id, 403, 'Non autorisé.');

        return $id;
    }
}