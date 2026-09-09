<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Models\Proprietaire;
use Illuminate\Http\Request;
use App\Http\Resources\PayoutResource;
use App\Services\LandlordEarningsService;

class PayoutController extends Controller
{
    protected $landlordEarningsService;

    public function __construct(LandlordEarningsService $landlordEarningsService)
    {
        $this->landlordEarningsService = $landlordEarningsService;
    }

    /**
     * GET /api/proprietaire/payouts
     * Liste l'historique des versements du propriétaire connecté
     */
    public function index(Request $request)
    {
        $proprietaire = $request->user()->proprietaire;
        $payouts = Payout::where('proprietaire_id', $proprietaire->id)
            ->orderByDesc('created_at')
            ->get();

        return PayoutResource::collection($payouts);
    }

    /**
     * GET /api/proprietaire/payouts/{id}
     * Détails d'un versement spécifique
     */
    public function show(Request $request, Payout $payout)
    {
        // Vérification d'appartenance
        if ($payout->proprietaire_id !== $request->user()->proprietaire->id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        return new PayoutResource($payout);
    }

    /**
     * GET /api/proprietaire/earnings
     * Retourne les gains actuels en attente de versement
     */
    public function currentEarnings(Request $request)
    {
        $proprietaire = $request->user()->proprietaire;
        $now = now();

        // Période en cours (du début du mois à aujourd'hui)
        $periodStart = $now->startOfMonth();
        $periodEnd = $now;

        // Vérifier s'il y a déjà un versement en attente/en cours pour cette période
        $existingPayout = Payout::where('proprietaire_id', $proprietaire->id)
            ->where('period_start', $periodStart->toDateString())
            ->where('period_end', $periodEnd->toDateString())
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($existingPayout) {
            return new PayoutResource($existingPayout->fresh());
        }

        // Sinon, calculer les gains actuels (sans créer de versement encore)
        $earningsCalculation = $this->landlordEarningsService->calculateEarnings(
            $proprietaire,
            $periodStart,
            $periodEnd
        );

        // Retourner un calcul théorique (pas un enregistrement réel)
        return response()->json([
            'type' => 'earnings_estimate',
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'gross_amount' => $earningsCalculation['gross_amount'],
            'payments_count' => $earningsCalculation['payments_count'],
            'fee_breakdown' => $earningsCalculation['fee_breakdown'],
            'net_amount_to_payout' => $earningsCalculation['net_amount_to_payout'],
            'message' => 'Ce sont vos gains estimés pour la période en cours. Un versement sera créé automatiquement selon votre planning de versement.',
        ]);
    }
}