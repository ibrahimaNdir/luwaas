<?php

namespace Tests\Feature;

use App\Models\Bail;
use App\Models\Logement;
use App\Models\Locataire;
use App\Models\Paiement;
use App\Models\Proprietaire;
use App\Models\Propriete;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BailService;
use App\Services\PaymentService;
use App\Services\WebhookService;
use App\Contracts\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class RentPaymentMultiMonthTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function un_paiement_de_plusieurs_mois_valide_toutes_les_echeances_correspondantes(): void
    {
        // ── Géographie (requise par la table proprietes)
        $region    = \App\Models\Region::create(['nom' => 'Dakar', 'code' => 'DK']);
        $dept      = \App\Models\Departement::create(['nom' => 'Dakar', 'code' => 'DK01', 'region_id' => $region->id]);
        $commune   = \App\Models\Commune::create(['nom' => 'Plateau', 'code' => 'PLT', 'departement_id' => $dept->id]);

        // ── Propriétaire
        $userProp     = User::factory()->proprietaire()->create();
        $proprietaire = Proprietaire::create([
            'user_id'         => $userProp->id,
            'proprietaire_id' => 'PROP-1',
            'is_actif'        => true,
        ]);

        // ── Propriété & Logement
        $propriete = Propriete::create([
            'proprietaire_id' => $proprietaire->id,
            'titre'           => 'Résidence Test',
            'type'            => 'immeuble',
            'region_id'       => $region->id,
            'departement_id'  => $dept->id,
            'commune_id'      => $commune->id,
        ]);

        $logement = Logement::create([
            'propriete_id'         => $propriete->id,
            'numero'               => 'A1',
            'typelogement'         => 'appartement',
            'nombre_chambres'      => 2,
            'nombre_salles_de_bain' => 1,
            'prix_loyer'           => 100000,
            'statut_occupe'        => 'occupe',
            'statut_publication'   => 'publie',
        ]);

        // ── Locataire
        $userLoc   = User::factory()->locataire()->create();
        $locataire = Locataire::create([
            'user_id'      => $userLoc->id,
            'locataire_id' => 'LOC-1',
            'is_actif'     => true,
        ]);

        // ── Bail
        $bail = Bail::create([
            'logement_id'             => $logement->id,
            'locataire_id'            => $locataire->id,
            'proprietaire_id'         => $proprietaire->id,
            'montant_loyer'           => 100000,
            'nombre_mois_caution'     => 1,
            'montant_caution_total'   => 100000,
            'montant_caution_signature' => 100000,
            'jour_echeance'           => 5,
            'date_debut'              => now()->toDateString(),
            'date_fin'                => now()->addYear()->toDateString(),
            'statut'                  => 'actif',
        ]);

        // ── Échéances Août et Septembre
        $paiementAout = Paiement::create([
            'bail_id'         => $bail->id,
            'locataire_id'    => $locataire->id,
            'type'            => 'loyer_mensuel',
            'montant_attendu' => 100000,
            'montant_paye'    => 0,
            'montant_restant' => 100000,
            'statut'          => 'impayé',
            'date_echeance'   => '2026-08-05',
        ]);

        $paiementSept = Paiement::create([
            'bail_id'         => $bail->id,
            'locataire_id'    => $locataire->id,
            'type'            => 'loyer_mensuel',
            'montant_attendu' => 100000,
            'montant_paye'    => 0,
            'montant_restant' => 100000,
            'statut'          => 'impayé',
            'date_echeance'   => '2026-09-05',
        ]);

        // ── Transaction couvrant 2 mois (200 000 FCFA = 2 × 100 000)
        $transaction = Transaction::create([
            'type'          => 'rent_payment',
            'paiement_id'   => $paiementAout->id,
            'paydunyatoken' => 'TOKEN_2_MOIS',
            'montant'       => 200000,
            'statut'        => 'en_attente',
            'mode_paiement' => 'wave',
        ]);

        // ── Mocks
        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('verifyWebhook')->andReturn(true);
        $gateway->shouldReceive('normalizeWebhookPayload')->andReturn([
            'token'           => 'TOKEN_2_MOIS',
            'status'          => 'completed',
            'amount'          => 200000,
            'transaction_ref' => 'REF-123',
        ]);

        $bailService    = Mockery::mock(BailService::class);
        $paymentService = Mockery::mock(PaymentService::class);
        $paymentService->shouldReceive('redistributeToBailleur')->andReturnNull();

        $webhookService = new WebhookService(
            $bailService,
            $paymentService,
            $gateway,
            app(\App\Services\CommissionService::class)
        );

        $request  = Request::create('/webhook/paydunya', 'POST', ['token' => 'TOKEN_2_MOIS']);
        $response = $webhookService->handle($request);

        // ── Assertions
        $this->assertEquals(200, $response['status']);

        // Août → payé
        $this->assertSame('payé', $paiementAout->fresh()->statut);
        $this->assertEquals(100000, $paiementAout->fresh()->montant_paye);
        $this->assertEquals(0, $paiementAout->fresh()->montant_restant);

        // Septembre → payé automatiquement grâce au surplus
        $this->assertSame('payé', $paiementSept->fresh()->statut);
        $this->assertEquals(100000, $paiementSept->fresh()->montant_paye);
        $this->assertEquals(0, $paiementSept->fresh()->montant_restant);
    }
}
