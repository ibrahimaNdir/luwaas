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
                // active, trial, expired, cancelled
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

        // Crée le nouvel abonnement
        $subscription = $proprietaire->subscriptions()->create([
            'plan_id'    => $plan->id,
            'status'     => 'active',
            'amount'     => $plan->price_xof, // ✅ obligatoire
            'starts_at'  => now(),
            'ends_at'    => $plan->billing_cycle === 'yearly'
                ? now()->addYear()
                : now()->addMonth(), // ✅ respecte le cycle
        ]);

        // Met à jour le propriétaire
        $proprietaire->update([
            'plan'                 => $plan->name,
            'subscription_status'  => 'active',
            'subscription_ends_at' => now()->addMonth(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Plan changé vers {$plan->name} avec succès.",
            'data'    => $subscription
        ]);
    }

    /**
     * Annuler l'abonnement d'un propriétaire
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
}
