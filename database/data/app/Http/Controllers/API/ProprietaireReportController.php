<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Exports\FinancialReportExport;
use Maatwebsite\Excel\Facades\Excel;

class ProprietaireReportController extends Controller
{
    public function getFinances(Request $request)
    {
        $proprietaireId = $this->proprietaireId($request);
        $mois = $request->query('mois', Carbon::now()->month);
        $annee = $request->query('annee', Carbon::now()->year);

        $paiements = Paiement::whereHas('bail', function ($q) use ($proprietaireId) {
            $q->where('proprietaire_id', $proprietaireId);
        })
        ->whereMonth('date_echeance', $mois)
        ->whereYear('date_echeance', $annee)
        ->get();

        $totalAttendu = $paiements->sum('montant_total');
        $totalPercu = $paiements->where('statut', 'payé')->sum('montant_paye');
        $totalEnRetard = $paiements->where('statut', 'impayé')->sum('montant_restant');

        return response()->json([
            'mois' => $mois,
            'annee' => $annee,
            'statistiques' => [
                'total_attendu' => $totalAttendu,
                'total_percu' => $totalPercu,
                'total_en_retard' => $totalEnRetard,
            ],
            'paiements' => $paiements
        ]);
    }

    public function exportFinances(Request $request)
    {
        $proprietaire = $request->user()->proprietaire;
        
        if (!$proprietaire || !$proprietaire->canUseFeature('Export Excel')) {
            return response()->json(['message' => 'Cette fonctionnalité nécessite le plan Pro.'], 403);
        }

        $proprietaireId = $proprietaire->id;
        $mois = $request->query('mois', Carbon::now()->month);
        $annee = $request->query('annee', Carbon::now()->year);

        return Excel::download(
            new FinancialReportExport($proprietaireId, $mois, $annee), 
            "rapport_financier_{$mois}_{$annee}.xlsx"
        );
    }

    private function proprietaireId(Request $request): int
    {
        $id = $request->user()->proprietaire->id ?? null;
        abort_if(!$id, 403, 'Non autorisé.');
        return $id;
    }
}
