<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Plan;
use App\Models\Proprietaire;
use Illuminate\Http\Request;

class AdminSubscriptionController extends Controller
{
    /**
     * Liste tous les abonnements
     */
    public function index(Request $request)
    {
        $subscriptions = Subscription::with([
            'proprietaire.user',
            'plan'
        ])
            ->when($request->status, function ($query, $status) {
                // active | pending | expired | cancelled | failed
                $query->where('status', $status);
            })
            ->when($request->plan_id, function ($query, $planId) {
                $query->where('plan_id', $planId);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data'    => $subscriptions
        ]);
    }

    /**
     * Liste tous les plans disponibles
     */
    public function plans()
    {
        $plans = Plan::withCount('subscriptions')
            ->get()
            ->map(function ($plan) {
                $plan->abonnes_actifs = $plan->subscriptions()
                    ->where('status', 'active')
                    ->count();
                return $plan;
            });

        return response()->json([
            'success' => true,
            'data'    => $plans
        ]);
    }

    /**
     * Changer le plan d'un propriétaire (offrir upgrade/downgrade)
     */
    public function changePlan(Request $request, string $proprietaireId)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id'
        ]);

        $proprietaire = Proprietaire::findOrFail($proprietaireId);
        $plan         = Plan::findOrFail($request->plan_id);

        // Annule l'abonnement actif en cours
        $proprietaire->subscriptions()
            ->where('status', 'active')
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        // Downgrade vers le plan gratuit → essai 15 jours
        if ($plan->tier === 'free') {
            $proprietaire->update([
                'plan'                 => 'free',
                'billing_cycle'        => null,
                'subscription_status'  => 'free_trial',
                'trial_ends_at'        => now()->addDays(15),
                'subscription_ends_at' => null,
                'cancelled_at'         => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Plan changé vers gratuit (essai 15 jours) avec succès.',
                'data'    => $proprietaire->fresh(),
            ]);
        }

        $endsAt = $plan->billing_cycle === 'yearly'
            ? now()->addYear()
            : now()->addMonth();

        // Crée le nouvel abonnement Pro
        $subscription = $proprietaire->subscriptions()->create([
            'plan_id'    => $plan->id,
            'status'     => 'active',
            'amount'     => $plan->price_xof,
            'starts_at'  => now(),
            'ends_at'    => $endsAt,
        ]);

        $proprietaire->update([
            'plan'                 => $plan->tier,
            'billing_cycle'        => $plan->billing_cycle,
            'subscription_status'  => 'active',
            'subscription_ends_at' => $endsAt,
            'trial_ends_at'        => null,
            'cancelled_at'         => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Plan changé vers {$plan->tier} avec succès.",
            'data'    => $subscription
        ]);
    }

    /**
     * Annuler l'abonnement d'un propriétaire.
     * Le bailleur conserve l'accès Pro jusqu'à subscription_ends_at (grace period).
     */
    public function cancel(string $proprietaireId)
    {
        $proprietaire = Proprietaire::findOrFail($proprietaireId);

        $proprietaire->subscriptions()
            ->where('status', 'active')
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $proprietaire->update([
            'subscription_status' => 'cancelled',
            'cancelled_at'        => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Abonnement annulé avec succès.',
        ]);
    }

    /**
     * Créer un nouveau plan d'abonnement
     */
    public function storePlan(Request $request)
    {
        $validated = $request->validate([
            'slug'             => 'required|string|unique:plans,slug',
            'name'             => 'required|string|max:255',
            'tier'             => 'required|string|max:100',
            'billing_cycle'    => 'nullable|in:monthly,yearly',
            'price_xof'        => 'required|numeric|min:0',
            'publications_max' => 'nullable|integer|min:1',
            'features'         => 'nullable|array',
            'is_active'        => 'nullable|boolean',
        ]);

        $plan = Plan::create($validated);

        return response()->json([
            'success' => true,
            'message' => "Plan '{$plan->name}' créé avec succès.",
            'data'    => $plan,
        ], 201);
    }

    /**
     * Modifier un plan d'abonnement existant
     */
    public function updatePlan(Request $request, string $id)
    {
        $plan = Plan::findOrFail($id);

        $validated = $request->validate([
            'name'             => 'sometimes|string|max:255',
            'tier'             => 'sometimes|string|max:100',
            'billing_cycle'    => 'nullable|in:monthly,yearly',
            'price_xof'        => 'sometimes|numeric|min:0',
            'publications_max' => 'nullable|integer|min:1',
            'features'         => 'nullable|array',
            'is_active'        => 'sometimes|boolean',
        ]);

        $plan->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Plan '{$plan->name}' mis à jour avec succès.",
            'data'    => $plan,
        ]);
    }

    /**
     * Activer / Désactiver un plan d'abonnement
     */
    public function togglePlan(string $id)
    {
        $plan = Plan::findOrFail($id);
        $plan->update(['is_active' => !$plan->is_active]);

        $statut = $plan->is_active ? 'activé' : 'désactivé';

        return response()->json([
            'success' => true,
            'message' => "Plan '{$plan->name}' {$statut} avec succès.",
            'data'    => $plan,
        ]);
    }
}
