<?php

namespace App\Exports;

use App\Models\Paiement;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FinancialReportExport implements FromCollection, WithHeadings, WithMapping
{
    protected $proprietaireId;
    protected $mois;
    protected $annee;

    public function __construct(int $proprietaireId, int $mois, int $annee)
    {
        $this->proprietaireId = $proprietaireId;
        $this->mois = $mois;
        $this->annee = $annee;
    }

    public function collection()
    {
        return Paiement::with(['bail.logement', 'locataire.user'])
            ->whereHas('bail', function ($q) {
                $q->where('proprietaire_id', $this->proprietaireId);
            })
            ->whereMonth('date_echeance', $this->mois)
            ->whereYear('date_echeance', $this->annee)
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID Paiement',
            'Logement',
            'Locataire',
            'Mois de',
            'Montant Attendu (FCFA)',
            'Montant Payé (FCFA)',
            'Montant Restant (FCFA)',
            'Statut',
            'Date de paiement',
        ];
    }

    public function map($paiement): array
    {
        return [
            $paiement->id,
            ($paiement->bail->logement->numero ?? 'N/A') . ' - ' . ($paiement->bail->logement->typelogement ?? ''),
            ($paiement->locataire->user->prenom ?? '') . ' ' . ($paiement->locataire->user->nom ?? ''),
            $paiement->mois_concerne,
            $paiement->montant_total,
            $paiement->montant_paye,
            $paiement->montant_restant,
            $paiement->statut,
            $paiement->date_paiement ? \Carbon\Carbon::parse($paiement->date_paiement)->format('d/m/Y') : 'Non payé',
        ];
    }
}
