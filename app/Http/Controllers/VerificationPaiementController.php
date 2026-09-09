<?php

namespace App\Http\Controllers;

use App\Models\Paiement;
use Illuminate\Http\Request;

class VerificationPaiementController extends Controller
{
    /**
     * GET /verifier/paiement/{token}
     *
     * Page HTML publique de vérification d'une quittance Luwaas via QR code.
     * Données exposées volontairement minimales (confidentialité).
     */
    public function verifier(string $token)
    {
        $paiement = Paiement::with([
            'locataire.user',
            'bail.logement',
            'transactions',
        ])
        ->where('verification_token', $token)
        ->where('statut', 'payé')
        ->first();

        if (!$paiement) {
            return view('verification.paiement', [
                'valide'  => false,
                'token'   => $token,
            ]);
        }

        // Nom tronqué : "Ibrahima N." — prénom complet + initiale du nom
        $user      = $paiement->locataire->user;
        $prenom    = $user->prenom ?? '';
        $nom       = $user->nom ?? '';
        $nomTronque = trim($prenom . ' ' . strtoupper(substr($nom, 0, 1)) . '.');

        // Certifié = paiement en ligne (pas manuel)
        $estCertifie = $paiement->est_certifie;

        return view('verification.paiement', [
            'valide'         => true,
            'token'          => $token,
            'montant'        => number_format((float) $paiement->montant_paye, 0, ',', ' '),
            'date_paiement'  => $paiement->date_paiement
                ? \Carbon\Carbon::parse($paiement->date_paiement)->translatedFormat('d F Y')
                : \Carbon\Carbon::parse($paiement->updated_at)->translatedFormat('d F Y'),
            'periode'        => $paiement->periode ?? 'Non précisée',
            'nom_locataire'  => $nomTronque ?: 'Locataire',
            'est_certifie'   => $estCertifie,
            'type_paiement'  => $estCertifie ? 'En ligne (certifié Luwaas)' : 'Manuel (déclaration bailleur)',
        ]);
    }
}
