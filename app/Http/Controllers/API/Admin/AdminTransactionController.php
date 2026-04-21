<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Paiement;
use Illuminate\Http\Request;

class AdminTransactionController extends Controller
{
    /**
     * Liste toutes les transactions
     */
    public function index(Request $request)
    {
        $transactions = Transaction::with([
                'paiement.locataire.user',
                'paiement.bail.logement'
            ])
            ->when($request->statut, function ($query, $statut) {
                // success, failed, pending
                $query->where('statut', $statut);
            })
            ->when($request->mode_paiement, function ($query, $mode) {
                // wave, orange_money, free_money ...
                $query->where('mode_paiement', $mode);
            })
            ->when($request->date_debut, function ($query, $date) {
                $query->whereDate('date_transaction', '>=', $date);
            })
            ->when($request->date_fin, function ($query, $date) {
                $query->whereDate('date_transaction', '<=', $date);
            })
            ->orderBy('date_transaction', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data'    => $transactions
        ]);
    }

    /**
     * Détail d'une transaction
     */
    public function show(string $id)
    {
        $transaction = Transaction::with([
            'paiement.locataire.user',
            'paiement.bail.logement.propriete.proprietaire.user'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $transaction
        ]);
    }

    /**
     * Résumé financier global
     */
    public function summary()
    {
        $now = now();

        $data = [
            // Ce mois
            'total_ce_mois'        => Transaction::where('statut', 'success')
                                        ->whereMonth('date_transaction', $now->month)
                                        ->whereYear('date_transaction', $now->year)
                                        ->sum('montant'),

            'nombre_ce_mois'       => Transaction::where('statut', 'success')
                                        ->whereMonth('date_transaction', $now->month)
                                        ->whereYear('date_transaction', $now->year)
                                        ->count(),

            // Global
            'total_global'         => Transaction::where('statut', 'success')->sum('montant'),
            'total_echouees'       => Transaction::where('statut', 'failed')->count(),
            'total_en_attente'     => Transaction::where('statut', 'pending')->count(),

            // Par mode de paiement
            'par_mode_paiement'    => Transaction::where('statut', 'success')
                                        ->selectRaw('mode_paiement, SUM(montant) as total, COUNT(*) as nombre')
                                        ->groupBy('mode_paiement')
                                        ->get(),

            // Paiements loyer en retard
            'loyers_en_retard'     => Paiement::where('statut', 'en_retard')->count(),
            'loyers_en_attente'    => Paiement::where('statut', 'en_attente')->count(),

            // Évolution 6 derniers mois
            'evolution_6_mois'     => Transaction::where('statut', 'success')
                                        ->where('date_transaction', '>=', $now->copy()->subMonths(6))
                                        ->selectRaw("TO_CHAR(date_transaction, 'YYYY-MM') as mois, SUM(montant) as total")
                                        ->groupBy('mois')
                                        ->orderBy('mois')
                                        ->get(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $data
        ]);
    }
}