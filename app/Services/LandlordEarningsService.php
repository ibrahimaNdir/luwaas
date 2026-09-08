<?php

namespace App\Services;

use App\Models\Paiement;
use App\Models\Proprietaire;
use App\Models\Transaction;
use App\Services\PspFeeService;
use App\Services\GatewayResolver;
use App\Models\PlatformSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class LandlordEarningsService
{
    protected $pspFeeService;
    protected $gatewayResolver;

    public function __construct(PspFeeService $pspFeeService, GatewayResolver $gatewayResolver)
    {
        $this->pspFeeService = $pspFeeService;
        $this->gatewayResolver = $gatewayResolver;
    }

    /**
     * Calcule les gains d'un propriétaire pour une période donnée selon le nouveau modèle
     * où Luwaas absorbe tous les frais techniques et ne préleve que sa commission commerciale
     *
     * @param Proprietaire $proprietaire
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return array Détails des gains calculés
     * @throws \Exception Si aucune méthode de versement par défaut n'est configurée
     */
    public function calculateEarnings(Proprietaire $proprietaire, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        // Période par défaut : mois en cours
        if (!$startDate) {
            $startDate = Carbon::now()->startOfMonth();
        }
        if (!$endDate) {
            $endDate = Carbon::now()->endOfMonth();
        }

        // Récupérer tous les paiements marqués 'payé' du propriétaire sur la période
        // avec leur transaction associée pour avoir les détails du gateway et mode
        $paiements = Paiement::whereHas('bail.logement.propriete', function ($query) use ($proprietaire) {
            $query->where('proprietaire_id', $proprietaire->id);
        })
        ->where('statut', 'payé')
        ->whereBetween('date_paiement', [$startDate, $endDate])
        ->with(['transactions' => function ($query) {
            $query->where('statut', 'payé'); // Seulement les transactions réussies
        }])
        ->get();

        $grossAmount = 0;
        $totalPayinFeeExpected = 0;
        $weightedPayinRateSum = 0; // Pour calculer la moyenne pondérée

        // Parcourir chaque paiement et sa transaction associée
        // Tracker les gateways utilisés pour s'assurer qu'ils sont tous les mêmes (requis par la contrainte d'unicité)
        $gatewaysUsed = [];

        foreach ($paiements as $paiement) {
            // On suppose une transaction réussie par paiement (validée dans le flujo de paiement)
            $transaction = $paiement->transactions->first();

            if (!$transaction) {
                Log::warning("Aucune transaction trouvée pour le paiement {$paiement->id}");
                continue;
            }

            $amount = $transaction->montant;
            $grossAmount += $amount;
            $gatewaysUsed[] = $transaction->gateway_used;

            // Calculer les frais payin pour cette transaction spécifique
            try {
                $payinFeeData = $this->pspFeeService->getPspRate(
                    $transaction->gateway_used,
                    $transaction->mode_paiement,
                    'payin',
                    $transaction->date_transaction
                );

                $payinFeeExpected = $this->pspFeeService->calculateExpectedPspFee($amount, $payinFeeData);
                $totalPayinFeeExpected += $payinFeeExpected;
                $weightedPayinRateSum += $amount * $payinFeeData['rate_percent']; // Somme de (montant * taux)
            } catch (\Exception $e) {
                Log::error("Impossible de déterminer le taux de frais payin pour la transaction {$transaction->id}: " . $e->getMessage());
                // En cas d'erreur, on considère 0% de frais (à améliorer selon les exigences produit)
                $payinFeeExpected = 0;
            }
        }

        // Vérifier que tous les gateways utilisés sont les mêmes (requis par la contrainte d'unicité des payouts)
        if (count(array_unique($gatewaysUsed)) > 1) {
            Log::warning("Plusieurs gateways détectés pour les transactions du propriétaire {$proprietaire->id} sur la période: " . implode(', ', array_unique($gatewaysUsed)) . ". Utilisation du premier gateway trouvé.");
        }

        // Calculer la commission commerciale Luwaas : 6% du brut avec seuil minimum de 6 000 FCFA
        $luwaasCommission = max($grossAmount * config('luwaas.commission_rate', 0.06), config('luwaas.commission_min', 6000));

        // Montant net dû au propriétaire (brut moins commission Luwaas)
        $netAmountToOwner = $grossAmount - $luwaasCommission;

        // Déterminer le canal de versement par défaut du propriétaire
        $defaultPayoutMethod = $proprietaire->payoutMethods()
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if (!$defaultPayoutMethod) {
            throw new \Exception("Aucune méthode de versement par défaut active configurée pour le propriétaire {$proprietaire->id}");
        }

        // Utiliser la date de fin de période pour les taux historiques des frais de versement
        $referenceDate = $endDate->format('Y-m-d');

        // Déterminer le gateway actif pour les nouveaux versements (ce qui sera utilisé pour ce payout)
        $activeGateway = $this->gatewayResolver->getActiveGateway();
        $activeGatewayKey = $activeGateway->getName();

        // Calculer les frais de versement que Luwaas absorbera
        try {
            $payoutFeeData = $this->pspFeeService->getPspRate(
                $activeGatewayKey,
                $defaultPayoutMethod->payout_channel,
                'payout',
                $referenceDate
            );
            $payoutFeeExpected = $this->pspFeeService->calculateExpectedPspFee($netAmountToOwner, $payoutFeeData);
        } catch (\Exception $e) {
            Log::error("Impossible de déterminer le taux de frais de versement pour le versement: " . $e->getMessage());
            $payoutFeeExpected = 0;
            $payoutFeeData = ['rate_percent' => 0.0, 'fixed_fee' => 0.0];
        }

        // Calculer le taux payin appliqué (moyenne pondérée)
        $payinFeeRateApplied = $grossAmount > 0 ? ($weightedPayinRateSum / $grossAmount) : 0.0;

        // Taux de versement appliqué (celui utilisé ci-dessus)
        $payoutFeeRateApplied = $payoutFeeData['rate_percent'] ?? 0.0;

        // Taux de commission Luwaas appliqué (fixe pour maintenant, pourrait venir de configuration)
        $luwaasCommissionRateApplied = config('luwaas.commission_rate', 0.06); // 6%

        // Bénéfice net de Luwaas pour cette transaction
        // = commission commerciale moins les frais techniques absorbés
        $luwaasNetBenefit = $luwaasCommission - ($totalPayinFeeExpected + $payoutFeeExpected);

        return [
            'proprietaire_id' => $proprietaire->id,
            'period_start' => $startDate->toDateString(),
            'period_end' => $endDate->toDateString(),
            'gross_amount' => $grossAmount,
            'luwaas_commission' => $luwaasCommission,
            'net_amount_to_owner' => $netAmountToOwner,
            'payin_fee_absorbed' => $totalPayinFeeExpected,
            'payout_fee_absorbed' => $payoutFeeExpected,
            'luwaas_net_benefit' => $luwaasNetBenefit,
            // Taux pour l'historisation dans le versement
            'payin_fee_rate_applied' => $payinFeeRateApplied,
            'payout_fee_rate_applied' => $payoutFeeRateApplied,
            'luwaas_commission_rate_applied' => $luwaasCommissionRateApplied,
            'payments_count' => $paiements->count(),
            'default_payout_method' => $defaultPayoutMethod,
            'active_gateway_used' => $activeGatewayKey,
        ];
    }

    /**
     * Crée un enregistrement de versement en attente basé sur le calcul des gains
     *
     * @param Proprietaire $proprietaire
     * @param array $earningsCalculation Résultat du calcul des gains
     * @return Payout L'enregistrement de versement créé
     */
    public function createPendingPayout(Proprietaire $proprietaire, array $earningsCalculation): \App\Models\Payout
    {
        // Vérifier s'il existe déjà un versement en attente ou en cours pour cette période
        // Pour éviter les doublons lorsqu'un propriétaire reçoit plusieurs paiements dans la même période
        $existingPayout = \App\Models\Payout::where('proprietaire_id', $proprietaire->id)
            ->where('period_start', $earningsCalculation['period_start'])
            ->where('period_end', $earningsCalculation['period_end'])
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($existingPayout) {
            // Un versement existe déjà pour cette période - on le retourne tel quel
            return $existingPayout;
        }

        // Créer un nouvel enregistrement de versement en attente
        return \App\Models\Payout::create([
            'proprietaire_id' => $proprietaire->id,
            'gross_amount' => $earningsCalculation['gross_amount'],
            'luwaas_commission' => $earningsCalculation['luwaas_commission'],
            'net_amount_to_owner' => $earningsCalculation['net_amount_to_owner'],
            'payin_fee_absorbed' => $earningsCalculation['payin_fee_absorbed'],
            'payout_fee_absorbed' => $earningsCalculation['payout_fee_absorbed'],
            'luwaas_net_benefit' => $earningsCalculation['luwaas_net_benefit'],
            // Historisation des taux appliqués
            'payin_fee_rate_applied' => $earningsCalculation['payin_fee_rate_applied'],
            'payout_fee_rate_applied' => $earningsCalculation['payout_fee_rate_applied'],
            'luwaas_commission_rate_applied' => $earningsCalculation['luwaas_commission_rate_applied'],
            // Historisation du gateway utilisé pour le versement (même gateway utilisé pour payin et payout)
            'payout_gateway_used' => $earningsCalculation['active_gateway_used'],
            'payin_gateway_used' => $earningsCalculation['active_gateway_used'],
            'payout_method_id' => $earningsCalculation['default_payout_method']->id,
            'status' => 'pending',
            'period_start' => $earningsCalculation['period_start'],
            'period_end' => $earningsCalculation['period_end'],
            // Générer une référence unique pour ce versement
            'reference' => 'PAYOUT-' . strtoupper($proprietaire->id) . '-' .
                         $earningsCalculation['period_start'] . '-' .
                         $earningsCalculation['period_end'] . '-' .
                         strtoupper(substr(uniqid(), -6)),
        ]);
    }

    /**
     * Déclenche le calcul de versement pour le propriétaire associé à un paiement
     * Cette méthode vérifie s'il y a des gains à verser et crée un enregistrement de versement en attente
     *
     * @param Paiement $paiement
     * @return void
     */
    public function triggerPayoutCalculation(Paiement $paiement): void
    {
        // Éviter les doublons : ne pas créer de versement si un versement en attente/couvert déjà existe pour cette période
        // On considère qu'un versement couvre typiquement un mois calendaire
        $paymentDate = $paiement->date_paiement ?? now();
        $periodStart = $paymentDate->startOfMonth();
        $periodEnd = $paymentDate->endOfMonth();

        // Vérifier s'il existe déjà un versement (en attente ou complété) pour cette propriétaire et cette période
        $existingPayout = $paiement->bail->logement->propriete->proprietaire->payouts()
            ->where('period_start', $periodStart->toDateString())
            ->where('period_end', $periodEnd->toDateString())
            ->whereIn('status', ['pending', 'processing', 'completed'])
            ->first();

        if ($existingPayout) {
            // Un versement existe déjà pour cette période - on ne crée pas de doublon
            return;
        }

        // Calculer les gains et créer un versement en attente
        $proprietaire = $paiement->bail->logement->propriete->proprietaire;

        $earningsCalculation = $this->calculateEarnings(
            $proprietaire,
            $periodStart,
            $periodEnd
        );

        // Créer l'enregistrement de versement en attente
        $this->createPendingPayout($proprietaire, $earningsCalculation);

        Log::info("Versement en attente créé pour le propriétaire {$proprietaire->id}", [
            'paiement_id' => $paiement->id,
            'period' => $periodStart->format('Y-m'),
            'net_amount_to_owner' => $earningsCalculation['net_amount_to_owner'],
            'luwaas_net_benefit' => $earningsCalculation['luwaas_net_benefit'],
        ]);
    }
}