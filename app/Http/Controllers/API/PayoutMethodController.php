<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PayoutMethod;
use App\Models\Proprietaire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Http\Requests\PayoutMethodStoreRequest;
use App\Http\Requests\PayoutMethodUpdateRequest;

/**
 * @group Méthodes de versement
 */
class PayoutMethodController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $proprietaire = $request->user()->proprietaire;
        $methods = $proprietaire->payoutMethods()->get();

        return response()->json($methods);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PayoutMethodStoreRequest $request)
    {
        $proprietaire = $request->user()->proprietaire;

        $validated = $request->validated();

        // Normalize the phone number to E.164 format
        $phone = $this->normalizePhoneNumber($validated['payout_phone']);
        $validated['payout_phone'] = $phone;

        // Check for duplicate (same proprietaire, channel, phone)
        $exists = PayoutMethod::where('proprietaire_id', $proprietaire->id)
            ->where('payout_channel', $validated['payout_channel'])
            ->where('payout_phone', $phone)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Ce moyen de paiement existe déjà pour ce propriétaire.'
            ], 422);
        }

        $method = PayoutMethod::create(array_merge($validated, [
            'proprietaire_id' => $proprietaire->id,
            'is_active'       => true,
            'is_default'      => false, // by default, new methods are not set as default
            'verified_at'     => null,
        ]));

        return response()->json($method, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(PayoutMethod $payoutMethod)
    {
        // Ensure the method belongs to the authenticated proprietair
        if ($payoutMethod->proprietaire_id !== $this->getProprietaireId()) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        return response()->json($payoutMethod);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PayoutMethodUpdateRequest $request, PayoutMethod $payoutMethod)
    {
        // Ensure the method belongs to the authenticated proprietair
        if ($payoutMethod->proprietaire_id !== $this->getProprietaireId()) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $validated = $request->validated();

        // If phone is being updated, normalize it
        if (isset($validated['payout_phone'])) {
            $validated['payout_phone'] = $this->normalizePhoneNumber($validated['payout_phone']);
        }

        // Check for duplicate if channel or phone is being updated
        if (isset($validated['payout_channel']) || isset($validated['payout_phone'])) {
            $channel = $validated['payout_channel'] ?? $payoutMethod->payout_channel;
            $phone   = $validated['payout_phone'] ?? $payoutMethod->payout_phone;

            $exists = PayoutMethod::where('proprietaire_id', $this->getProprietaireId())
                ->where('payout_channel', $channel)
                ->where('payout_phone', $phone)
                ->where('id', '!=', $payoutMethod->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Ce moyen de paiement existe déjà pour ce propriétaire.'
                ], 422);
            }
        }

        $payoutMethod->update($validated);

        return response()->json($payoutMethod);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PayoutMethod $payoutMethod)
    {
        // Ensure the method belongs to the authenticated proprietair
        if ($payoutMethod->proprietaire_id !== $this->getProprietaireId()) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        // Check if the method has been used in any payout (historical data)
        $usedInPayout = $payoutMethod->payouts()->exists();

        if ($usedInPayout) {
            // Instead of deleting, we deactivate it to preserve history
            $payoutMethod->update(['is_active' => false]);

            // If this method was the default, we need to set another active method as default if available
            if ($payoutMethod->is_default) {
                $anotherActive = $this->getProprietaire()->payoutMethods()
                    ->where('is_active', true)
                    ->where('id', '!=', $payoutMethod->id)
                    ->first();

                if ($anotherActive) {
                    $anotherActive->update(['is_default' => true]);
                }
            }

            return response()->json(['message' => 'Moyen de paiement désactivé ( conservé pour l\'historique ).']);
        }

        // If not used in any payout, we can safely delete
        $payoutMethod->delete();

        return response()->json(null, 204);
    }

    /**
     * Set the specified method as the default for the proprietair.
     */
    public function setDefault(Request $request, PayoutMethod $payoutMethod)
    {
        // Ensure the method belongs to the authenticated proprietair
        if ($payoutMethod->proprietaire_id !== $this->getProprietaireId()) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        // Ensure the method is active
        if (!$payoutMethod->is_active) {
            return response()->json(['message' => 'Le moyen de paiement doit être actif pour être défini comme défaut.'], 422);
        }

        // Use a transaction to ensure consistency
        \DB::transaction(function () use ($payoutMethod) {
            // First, reset all methods for this proprietair to non-default
            $this->getProprietaire()->payoutMethods()->update(['is_default' => false]);

            // Then set the selected method as default
            $payoutMethod->update(['is_default' => true]);
        });

        return response()->json($payoutMethod->fresh());
    }

    /**
     * Get the authenticated proprietair ID.
     */
    protected function getProprietaireId(): int
    {
        return $this->getProprietaire()->id;
    }

    /**
     * Get the authenticated proprietair.
     */
    protected function getProprietaire(): Proprietaire
    {
        return request()->user()->proprietaire;
    }

    /**
     * Normalize a phone number to E.164 format.
     * Supports formats: +221771234567, 221771234567, 77 123 45 67
     */
    protected function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-digit characters
        $digits = preg_replace('/\D/', '', $phone);

        // If the number starts with 00, remove them (international prefix)
        if (substr($digits, 0, 2) === '00') {
            $digits = substr($digits, 2);
        }

        // If the number starts with 0 (national prefix), remove it
        if (substr($digits, 0, 1) === '0') {
            $digits = substr($digits, 1);
        }

        // If the number does not start with the country code for Senegal (221), prepend it
        if (substr($digits, 0, 3) !== '221') {
            $digits = '221' . $digits;
        }

        // Return in E.164 format with a plus sign
        return '+' . $digits;
    }
}