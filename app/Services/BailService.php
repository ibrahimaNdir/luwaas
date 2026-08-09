<?php

namespace App\Services;

use App\Events\BailCree;
use App\Models\Bail;
use App\Models\Demande;
use App\Models\Paiement;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage; 




class BailService
{
    public function __construct(
        protected DemandeService $demandeService
    ) {}
    /**
     * Vérifie que le propriétaire peut créer un bail depuis cette demande.
     */
    public function validerDemande(Demande $demande, int $proprietaireId): ?string
    {
        if ($demande->status !== 'acceptee') {
            return 'La demande doit être acceptée avant de créer un bail.';
        }

        if ($demande->proprietaire_id !== $proprietaireId) {
            return 'Cette demande ne vous concerne pas.';
        }

        if ($demande->logement->statut_occupe !== 'disponible') {
            return 'Ce logement doit être disponible pour créer un bail.';
        }

        $bailExistant = Bail::where('logement_id', $demande->logement_id)
            ->whereIn('statut', ['en_attente_paiement', 'actif'])
            ->exists();

        if ($bailExistant) {
            return 'Un bail actif existe déjà pour ce logement.';
        }

        return null;
    }

    /**
     * Crée le bail + le paiement de signature (conforme au plafond légal de caution).
     */
    public function creerBail(Demande $demande, array $data): Bail
    {
        return DB::transaction(function () use ($demande, $data) {
            $montantLoyer = $demande->logement->prix_loyer;
            $charges      = $demande->logement->charges_mensuelles ?? 0;

            $dureeBailMois = Carbon::parse($data['date_debut'])
                ->diffInMonths(Carbon::parse($data['date_fin']));

            $cautionCalc = $this->calculerCaution(
                $montantLoyer,
                $data['nombre_mois_caution'],
                $dureeBailMois
            );

            // À la signature : 1er loyer + 1 mois de caution (PAS la caution totale)
            $montantTotalSignature = $cautionCalc['montant_caution_signature'] + $montantLoyer;
            $periode = Carbon::parse($data['date_debut'])->isoFormat('MMMM YYYY');

            $bail = Bail::create([
                'logement_id'                => $demande->logement_id,
                'locataire_id'               => $demande->locataire_id,
                'proprietaire_id'            => $demande->proprietaire_id,
                'demande_id'                 => $demande->id,
                'montant_loyer'              => $montantLoyer,
                'charges_mensuelles'         => $charges,
                'nombre_mois_caution'        => $data['nombre_mois_caution'],
                'montant_caution_total'      => $cautionCalc['montant_caution_total'],
                'montant_caution_signature'  => $cautionCalc['montant_caution_signature'],
                'montant_caution_etale'      => $cautionCalc['montant_caution_etale'],
                'mensualite_caution'         => $cautionCalc['mensualite_caution'],
                'mois_etalement_restants'    => $cautionCalc['mois_etalement_restants'],
                'date_debut'                 => $data['date_debut'],
                'date_fin'                   => $data['date_fin'],
                'jour_echeance'              => $data['jour_echeance'],
                'renouvellement_automatique' => $data['renouvellement_automatique'],
                'conditions_speciales'       => $data['conditions_speciales'] ?? null,
                'statut'                     => 'en_attente_paiement',
            ]);

            Paiement::create([
                'locataire_id'    => $demande->locataire_id,
                'bail_id'         => $bail->id,
                'type'            => 'signature',
                'montant_attendu' => $montantTotalSignature,
                'montant_paye'    => 0,
                'montant_restant' => $montantTotalSignature,
                'statut'          => 'impayé',
                'date_echeance'   => now()->addDays(7),
                'periode'         => $periode,
            ]);

            $demande->update([
                'status'         => 'bail_cree',
                'date_bail_cree' => now(),
            ]);

            $proprietaire = \App\Models\Proprietaire::find($demande->proprietaire_id);
            if ($proprietaire && $proprietaire->subscription_status === 'free_trial') {
                $proprietaire->update([
                    'plan' => 'starter',
                    'subscription_status' => 'pending_payment',
                ]);
                Log::info("🔄 Bascule automatique du bailleur {$proprietaire->id} vers le plan starter.");
            }

            $this->demandeService->refuserAutomatiquementApresBail(
                $demande->logement_id,
                $demande->id
            );

            event(new BailCree($bail));

            Log::info("✅ Bail créé : ID {$bail->id}");

            return $bail;
        });
    }

    /**
     * Génère l'échéancier des paiements mensuels après activation du bail.
     * Pendant la période d'étalement, le loyer inclut la tranche de caution.
     * La dernière mensualité de caution absorbe l'écart d'arrondi pour que
     * le total collecté corresponde exactement au montant dû.
     */
    public function genererLoyersMensuels(Bail $bail): void
    {
        $dateDebut = Carbon::parse($bail->date_debut);
        $dateFin   = Carbon::parse($bail->date_fin);
        $moisTotal = $dateDebut->diffInMonths($dateFin);

        $moisEtalementRestants = $bail->mois_etalement_restants;
        $cautionEtaleeRestante = $bail->montant_caution_etale;

        for ($i = 1; $i <= $moisTotal; $i++) {
            $dateEcheance = $dateDebut->copy()->addMonths($i)->day($bail->jour_echeance ?? 5);

            $montant = $bail->montant_loyer + $bail->charges_mensuelles;

            if ($moisEtalementRestants > 0) {
                if ($moisEtalementRestants === 1) {
                    // Dernier mois d'étalement : on prend exactement ce qu'il reste
                    $trancheCaution = $this->arrondir($cautionEtaleeRestante);
                } else {
                    $trancheCaution = (int) $bail->mensualite_caution; // déjà arrondie
                    $cautionEtaleeRestante -= $trancheCaution;
                }

                $montant += $trancheCaution;
                $moisEtalementRestants--;
            }

            Paiement::create([
                'locataire_id'    => $bail->locataire_id,
                'bail_id'         => $bail->id,
                'type'            => 'loyer_mensuel',
                'montant_attendu' => $montant,
                'montant_paye'    => 0,
                'montant_restant' => $montant,
                'statut'          => 'impayé',
                'date_echeance'   => $dateEcheance,
                'periode'         => $dateEcheance->isoFormat('MMMM YYYY'),
            ]);
        }

        Log::info("✅ Échéancier généré pour bail {$bail->id}", ['nombre_mois' => $moisTotal]);
    }

    /**
     * Calcule la répartition de la caution selon la loi sénégalaise
     * (Décret n° 2023-382) : plafond 2 mois si loyer ≤ 500 000 FCFA.
     * 1 mois payé à la signature, le solde étalé sur la durée du bail (max 12 mois).
     * La mensualité est arrondie au multiple de 100 FCFA le plus proche.
     */
    private function calculerCaution(
        float $montantLoyer,
        int $nombreMoisDemande,
        int $dureeBailMois
    ): array {
        $plafondMois = $montantLoyer <= 500000 ? 2 : $nombreMoisDemande;
        $nombreMois  = min($nombreMoisDemande, $plafondMois);

        $cautionTotale = $montantLoyer * $nombreMois;

        $cautionSignature = $montantLoyer; // 1 mois à la signature
        $cautionEtalee    = max(0, $cautionTotale - $cautionSignature);

        if ($cautionEtalee <= 0) {
            $moisEtalement = 0;
        } elseif ($dureeBailMois <= 1) {
            $cautionSignature = $cautionTotale; // tout à la signature
            $cautionEtalee    = 0;
            $moisEtalement    = 0;
        } elseif ($dureeBailMois <= 12) {
            $moisEtalement = $dureeBailMois;
        } else {
            $moisEtalement = 12;
        }

        $mensualite = $moisEtalement > 0
            ? $this->arrondir($cautionEtalee / $moisEtalement)
            : 0;

        return [
            'montant_caution_total'     => $cautionTotale,
            'montant_caution_signature' => $cautionSignature,
            'montant_caution_etale'     => $cautionEtalee,
            'mensualite_caution'        => $mensualite,
            'mois_etalement_restants'   => $moisEtalement,
        ];
    }

    /**
     * Arrondit un montant FCFA au multiple de 100 le plus proche.
     */
    private function arrondir(float $montant, int $tolerance = 20): int
    {
        $arrondiCent = round($montant / 100) * 100;

        if (abs($arrondiCent - $montant) <= $tolerance) {
            return (int) $arrondiCent;
        }

        return (int) (round($montant / 5) * 5);
    }

    /**
     * Vérifie si l'utilisateur est locataire ou bailleur du bail.
     */
    public function verifierAcces(Bail $bail, int $userId): bool
    {
        $isLocataire = $bail->locataire?->user_id === $userId;
        $isBailleur  = $bail->logement->propriete->proprietaire?->user_id === $userId;

        return $isLocataire || $isBailleur;
    }

    public function genererEtStockerPdf(Bail $bail): void
    {
        $pdf = \PDF::loadView('bail_pdf', compact('bail'));
        $filename = "contrats/bail_{$bail->id}_" . now()->format('Ymd_His') . ".pdf";

        Storage::disk('local')->put($filename, $pdf->output());

        $bail->update(['document_pdf_path' => $filename]);
    }
}
