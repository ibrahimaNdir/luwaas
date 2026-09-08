<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Paiement;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

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
                // Supported payment methods: .implode(', ', config('luwaas.payment_methods.mobile_money'))...
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
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        $evolutionQuery = Transaction::whereIn('statut', ['valide', 'success'])
            ->where('date_transaction', '>=', $now->copy()->subMonths(6));

        if ($isSqlite) {
            $evolution = $evolutionQuery
                ->selectRaw("strftime('%Y-%m', date_transaction) as mois, SUM(montant) as total")
                ->groupBy('mois')
                ->orderBy('mois')
                ->get();
        } else {
            $evolution = $evolutionQuery
                ->selectRaw("DATE_FORMAT(date_transaction, '%Y-%m') as mois, SUM(montant) as total")
                ->groupBy('mois')
                ->orderBy('mois')
                ->get();
        }

        $data = [
            // Ce mois
            'total_ce_mois'     => Transaction::whereIn('statut', ['valide', 'success'])
                                        ->whereMonth('date_transaction', $now->month)
                                        ->whereYear('date_transaction', $now->year)
                                        ->sum('montant'),

            'nombre_ce_mois'    => Transaction::whereIn('statut', ['valide', 'success'])
                                        ->whereMonth('date_transaction', $now->month)
                                        ->whereYear('date_transaction', $now->year)
                                        ->count(),

            // Global
            'total_global'      => Transaction::whereIn('statut', ['valide', 'success'])->sum('montant'),
            'total_echouees'    => Transaction::whereIn('statut', ['rejete', 'failed'])->count(),
            'total_en_attente'  => Transaction::whereIn('statut', ['en_attente', 'pending'])->count(),

            // Par mode de paiement
            'par_mode_paiement' => Transaction::whereIn('statut', ['valide', 'success'])
                                        ->selectRaw('mode_paiement, SUM(montant) as total, COUNT(*) as nombre')
                                        ->groupBy('mode_paiement')
                                        ->get(),

            // Paiements loyer en retard
            'loyers_en_retard'  => Paiement::whereIn('statut', ['en_retard', 'impayé'])->count(),
            'loyers_en_attente' => Paiement::where('statut', 'en_attente')->count(),

            // Évolution 6 derniers mois
            'evolution_6_mois'     => Transaction::where('statut', 'success')
                                        ->where('date_transaction', '>=', $now->copy()->subMonths(6))
                                        ->selectRaw('YEAR(date_transaction) as annee, MONTH(date_transaction) as mois, SUM(montant) as total')
                                        ->groupBy('annee', 'mois')
                                        ->orderBy('annee', 'mois')
                                        ->get(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $data
        ]);
    }
}