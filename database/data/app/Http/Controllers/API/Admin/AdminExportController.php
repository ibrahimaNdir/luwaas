<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminExportController extends Controller
{
    /**
     * Export CSV de tous les utilisateurs
     */
    public function exportUsers(): StreamedResponse
    {
        $fileName = 'users_luwaas_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename={$fileName}",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            // En-tête UTF-8 BOM pour Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ['ID', 'Prénom', 'Nom', 'Email', 'Téléphone', 'Rôle', 'Statut Actif', 'Date Inscription'], ';');

            User::chunk(200, function ($users) use ($file) {
                foreach ($users as $user) {
                    fputcsv($file, [
                        $user->id,
                        $user->prenom,
                        $user->nom,
                        $user->email,
                        $user->telephone,
                        $user->user_type,
                        $user->is_active ? 'Oui' : 'Non',
                        $user->created_at?->format('Y-m-d H:i:s'),
                    ], ';');
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export CSV des transactions
     */
    public function exportTransactions(Request $request): StreamedResponse
    {
        $fileName = 'transactions_luwaas_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename={$fileName}",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $query = Transaction::with([
            'paiement.locataire.user',
            'subscription.proprietaire.user',
        ]);

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $callback = function () use ($query) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ['ID', 'Référence', 'Client', 'Type', 'Montant (FCFA)', 'Statut', 'Date Transaction'], ';');

            $query->chunk(200, function ($transactions) use ($file) {
                foreach ($transactions as $t) {
                    $user = $t->paiement?->locataire?->user
                        ?? $t->subscription?->proprietaire?->user;
                    $userNom = $user ? "{$user->prenom} {$user->nom}" : 'Inconnu';
                    fputcsv($file, [
                        $t->id,
                        $t->paydunyatoken ?? $t->id,
                        $userNom,
                        $t->type,
                        $t->montant,
                        $t->statut,
                        $t->created_at?->format('Y-m-d H:i:s'),
                    ], ';');
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
