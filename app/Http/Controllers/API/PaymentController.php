<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Models\Plan;
use App\Models\Transaction;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Bail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $paymentService) {}

    // ═══════════════════════════════════════════
    // PLANS
    // ═══════════════════════════════════════════

    public function plans()
    {
        return response()->json([
            'message' => 'Plans disponibles',
            'plans'   => $this->paymentService->getPlans(),
        ]);
    }

    // ═══════════════════════════════════════════
    // INITIER ABONNEMENT
    // ═══════════════════════════════════════════

    public function initierAbonnement(Request $request)
    {
        $proprietaire = $this->proprietaire($request);

        $validated = $request->validate([
            'plan_id'   => 'required|exists:plans,id',
            'operateur' => 'required|in:wave,orange_money,free_money',
            'telephone' => 'nullable|string|max:20',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        if ($plan->tier === 'trial') {
            return response()->json(['message' => 'Le plan trial est activé automatiquement.'], 422);
        }

        if ($proprietaire->hasActiveSubscription()) {
            return response()->json(['message' => 'Vous avez déjà un abonnement actif.'], 422);
        }

        $subscription = $this->paymentService->creerSubscription(
            $proprietaire,
            $validated['plan_id'],
            $validated['operateur']
        );

        $erreur = $this->paymentService->validerInitiationAbonnement($subscription);
        if ($erreur) {
            return response()->json(
                array_diff_key($erreur, ['status' => '']),
                $erreur['status']
            );
        }

        try {
            [$transaction, $paymentData] = $this->paymentService->initierAbonnement(
                $subscription,
                $validated['operateur'],
                $validated['telephone'] ?? null,
                $request->ip()
            );
        } catch (\Exception $e) {
            Log::error("❌ Erreur initiation abonnement : " . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de l\'initiation. Réessayez.'], 500);
        }

        return response()->json([
            'success'         => true,
            'message'         => 'Paiement abonnement initié.',
            'subscription_id' => $subscription->id,
            'transaction'     => [
                'id'                => $transaction->id,
                'type'              => $transaction->type,
                'reference'         => $transaction->reference,
                'paydunyaToken'     => $transaction->paydunyaToken,
                'montant'           => $transaction->montant,
                'operateur'         => $transaction->mode_paiement,
                'statut'            => $transaction->statut,
            ],
            'payment_data' => $paymentData,
        ], 201);
    }

    // ═══════════════════════════════════════════
    // ANNULER ABONNEMENT
    // ═══════════════════════════════════════════

    public function annulerAbonnement(Request $request)
    {
        $proprietaire = $this->proprietaire($request);

        if (!$proprietaire->hasActiveSubscription()) {
            return response()->json(['message' => 'Aucun abonnement actif à annuler.'], 422);
        }

        $annule = $this->paymentService->annulerAbonnement($proprietaire);

        if (!$annule) {
            return response()->json(['message' => 'Impossible d\'annuler l\'abonnement.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Abonnement annulé avec succès.']);
    }

    // ═══════════════════════════════════════════
    // RENOUVELER ABONNEMENT
    // ═══════════════════════════════════════════

    public function renouvelerAbonnement(Request $request)
    {
        $proprietaire = $this->proprietaire($request);

        $validated = $request->validate([
            'plan_id'   => 'required|exists:plans,id',
            'operateur' => 'required|in:wave,orange_money,free_money',
            'telephone' => 'nullable|string|max:20',
        ]);

        try {
            [$transaction, $paymentData] = $this->paymentService->renouvelerAbonnement(
                $proprietaire,
                $validated['plan_id'],
                $validated['operateur'],
                $validated['telephone'] ?? null,
                $request->ip()
            );
        } catch (\Exception $e) {
            Log::error("❌ Erreur renouvellement : " . $e->getMessage());
            return response()->json(['message' => 'Erreur lors du renouvellement. Réessayez.'], 500);
        }

        return response()->json([
            'success'     => true,
            'message'     => 'Renouvellement initié avec succès.',
            'transaction' => [
                'id'        => $transaction->id,
                'reference' => $transaction->reference,
                'montant'   => $transaction->montant,
                'statut'    => $transaction->statut,
            ],
            'payment_data' => $paymentData,
        ], 201);
    }


    // ═══════════════════════════════════════════
    // INITIER LOYER
    // ═══════════════════════════════════════════

    public function initierLoyer(Request $request, int $paiementId)
    {
        $locataireId = $this->locataireId($request);

        $validated = $request->validate([
            'operateur' => 'required|in:wave,orange_money,free_money',
            'telephone' => 'nullable|string|max:20',
        ]);

        $paiement = Paiement::with('bail')->findOrFail($paiementId);

        $erreur = $this->paymentService->validerInitiationLoyer($paiement, $locataireId);
        if ($erreur) {
            return response()->json(
                array_diff_key($erreur, ['status' => '']),
                $erreur['status']
            );
        }

        try {
            [$transaction, $paymentData] = $this->paymentService->initierLoyer(
                $paiement,
                $validated['operateur'],
                $validated['telephone'] ?? null,
                $request->ip()
            );
        } catch (\Exception $e) {
            Log::error("❌ Erreur initiation loyer : " . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de l\'initiation. Réessayez.'], 500);
        }

        return response()->json([
            'success'     => true,
            'message'     => 'Paiement loyer initié.',
            'transaction' => [
                'id'                => $transaction->id,
                'type'              => $transaction->type,
                'reference'         => $transaction->reference,
                'paydunyaToken'     => $transaction->paydunyaToken,
                'montant'           => $transaction->montant,
                'operateur'         => $transaction->mode_paiement,
                'statut'            => $transaction->statut,
            ],
            'payment_data' => $paymentData,
        ], 201);
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

    private function proprietaire(Request $request)
    {
        $proprietaire = $request->user()->proprietaire ?? null;
        abort_if(!$proprietaire, 403, 'Non autorisé.');
        return $proprietaire;
    }

    private function accesBailAutorise(Request $request, Bail $bail): bool
    {
        $locataireId    = $request->user()->locataire->id ?? null;
        $proprietaireId = $request->user()->proprietaire->id ?? null;

        return $bail->locataire_id === $locataireId || $bail->proprietaire_id === $proprietaireId;
    }



    // ═══════════════════════════════════════════
    // LISTE & DÉTAIL PAIEMENTS
    // ═══════════════════════════════════════════

    public function index(Request $request)
    {
        $locataireId = $this->locataireId($request);

        $paiements = Paiement::with('bail.logement')
            ->where('locataire_id', $locataireId)
            ->orderByDesc('date_echeance')
            ->get();

        return response()->json(['paiements' => $paiements]);
    }

    public function show(Request $request, int $id)
    {
        $paiement = Paiement::with('bail.logement')->findOrFail($id);

        $locataireId = $request->user()->locataire->id ?? null;
        $proprietaireId = $request->user()->proprietaire->id ?? null;

        $estLocataire = $paiement->locataire_id === $locataireId;
        $estProprietaire = $paiement->bail?->proprietaire_id === $proprietaireId;

        abort_if(!$estLocataire && !$estProprietaire, 403, 'Non autorisé.');

        return response()->json(['paiement' => $paiement]);
    }



    // ═══════════════════════════════════════════
    // PAIEMENTS — CONSULTATION
    // ═══════════════════════════════════════════


    public function paiementARegler(Request $request, int $bailId)
    {
        $bail = Bail::findOrFail($bailId);

        abort_if(!$this->accesBailAutorise($request, $bail), 403, 'Non autorisé.');

        $paiement = Paiement::where('bail_id', $bailId)
            ->whereIn('statut', ['impayé', 'partiel'])
            ->orderBy('date_echeance')
            ->first();

        if (!$paiement) {
            return response()->json(['message' => 'Aucun paiement en attente pour ce bail.'], 404);
        }

        return response()->json(['paiement' => $paiement]);
    }

    public function paiementsBail(Request $request, int $bailId)
    {
        $bail = Bail::findOrFail($bailId);

        abort_if(!$this->accesBailAutorise($request, $bail), 403, 'Non autorisé.');

        $paiements = Paiement::where('bail_id', $bailId)
            ->orderBy('date_echeance')
            ->get();

        return response()->json(['paiements' => $paiements]);
    }



    public function indexAdmin()
    {
        $paiements = Paiement::with(['bail.logement', 'locataire.user'])
            ->orderByDesc('date_echeance')
            ->get();

        return response()->json(['paiements' => $paiements]);
    }

    public function paiementsProprietaire(Request $request)
    {
        $proprietaireId = $this->proprietaire($request)->id;

        $paiements = Paiement::whereHas(
            'bail',
            fn($q) =>
            $q->where('proprietaire_id', $proprietaireId)
        )
            ->with('bail.logement')
            ->orderByDesc('date_echeance')
            ->get();

        return response()->json(['paiements' => $paiements]);
    }

    public function statistiques(Request $request)
    {
        $locataireId = $this->locataireId($request);

        $paiements = Paiement::where('locataire_id', $locataireId)->get();

        return response()->json([
            'total_paye'     => $paiements->where('statut', 'payé')->sum('montant_paye'),
            'total_impaye'   => $paiements->where('statut', 'impayé')->sum('montant_restant'),
            'nombre_payes'   => $paiements->where('statut', 'payé')->count(),
            'nombre_impayes' => $paiements->where('statut', 'impayé')->count(),
        ]);
    }

    // ═══════════════════════════════════════════
    // EXPORT PDF QUITTANCE
    // ═══════════════════════════════════════════

    public function exportQuittancePdf(Request $request, int $id)
    {
        $paiement = Paiement::with(['bail.logement.propriete.proprietaire.user', 'locataire.user'])->findOrFail($id);

        // Seuls le locataire et le propriétaire peuvent voir la quittance
        $locataireId = $request->user()->locataire->id ?? null;
        $proprietaireId = $request->user()->proprietaire->id ?? null;

        $estLocataire = $paiement->locataire_id === $locataireId;
        $estProprietaire = $paiement->bail?->proprietaire_id === $proprietaireId;

        abort_if(!$estLocataire && !$estProprietaire, 403, 'Non autorisé.');

        if ($paiement->statut !== 'payé') {
            return response()->json(['message' => 'Le paiement n\'est pas réglé.'], 422);
        }

        return PDF::loadView('pdf.quittance', compact('paiement'))
            ->download('Quittance_Loyer_' . $paiement->id . '.pdf');
    }

    // ═══════════════════════════════════════════
    // PAIEMENT MANUEL (HORS LIGNE)
    // ═══════════════════════════════════════════

    public function markAsPaidManually(Request $request, int $id)
    {
        $proprietaire = $this->proprietaire($request);
        $paiement = Paiement::with('bail.logement.propriete')->findOrFail($id);

        // Vérifier l'appartenance
        if ($paiement->bail?->proprietaire_id !== $proprietaire->id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        if ($paiement->statut === 'payé') {
            return response()->json(['message' => 'Le paiement est déjà réglé.'], 422);
        }

        DB::transaction(function () use ($paiement, $request) {
            // Créer une transaction bidon pour la traçabilité
            $transaction = Transaction::create([
                'type'             => 'rent_payment',
                'paiement_id'      => $paiement->id,
                'reference'        => 'MANUEL-' . strtoupper(uniqid()),
                'mode_paiement'    => $request->input('mode', 'especes'), // especes, wave_direct, etc.
                'montant'          => $paiement->montant_attendu,
                'statut'           => 'valide',
                'date_transaction' => now(),
            ]);

            $paiement->update([
                'statut'          => 'payé',
                'montant_paye'    => $paiement->montant_attendu,
                'montant_restant' => 0,
            ]);

            // Si c'est une signature, on active le bail
            if ($paiement->type === 'signature' && $paiement->bail) {
                $bail = $paiement->bail;
                $bail->update([
                    'statut'          => 'actif',
                    'date_activation' => now(),
                ]);
                $bail->logement->update(['statut_occupe' => 'occupe']);
                
                event(new \App\Events\BailSigne($bail));
                
                // On utilise resolve() pour éviter d'injecter BailService dans tout le controller
                $bailService = resolve(\App\Services\BailService::class);
                $bailService->genererLoyersMensuels($bail);
                $bailService->genererEtStockerPdf($bail);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Paiement marqué comme réglé manuellement.',
        ]);
    }
}
