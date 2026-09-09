<?php

namespace Tests\Feature;

use App\Models\Bail;
use App\Models\Locataire;
use App\Models\Logement;
use App\Models\Paiement;
use App\Models\Plan;
use App\Models\Proprietaire;
use App\Models\Propriete;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        config(['services.paydunya.master_key' => 'test-master-key']);
    }

    private function getValidHeaders(): array
    {
        return [
            'PAYDUNYA-MASTER-KEY' => hash('sha512', 'test-master-key')
        ];
    }

    /** @test */
    public function le_webhook_rejette_une_signature_invalide(): void
    {
        $response = $this->withHeaders([
            'PAYDUNYA-MASTER-KEY' => 'invalid-hash'
        ])->postJson('/api/webhook/paydunya', [
            'data' => [
                'status' => 'completed',
                'invoice' => ['token' => 'fake-token']
            ]
        ]);

        $response->assertStatus(403)
                 ->assertJson(['error' => 'Invalid signature']);
    }

    /** @test */
    public function le_webhook_valide_un_paiement_abonnement(): void
    {
        $user = User::factory()->proprietaire()->create();
        $proprietaire = Proprietaire::create([
            'user_id' => $user->id,
            'proprietaire_id' => 'PROP-WH1',
            'subscription_status' => 'expired',
            'is_actif' => true,
        ]);

        $plan = Plan::create([
            'slug' => 'pro-monthly',
            'name' => 'Pro',
            'tier' => 'pro',
            'billing_cycle' => 'monthly',
            'price_xof' => 5000,
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'proprietaire_id' => $proprietaire->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
            'amount' => 5000,
            'payment_gateway' => 'paydunya',
            'paydunya_token' => 'sub-token-123',
        ]);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'type' => 'subscription_payment',
            'montant' => 5000,
            'statut' => 'en_attente',
            'paydunyatoken' => 'sub-token-123',
            'payment_gateway' => 'paydunya',
            'mode_paiement' => 'wave',
        ]);

        $response = $this->withHeaders($this->getValidHeaders())
                         ->postJson('/api/webhook/paydunya', [
                             'data' => [
                                 'status' => 'completed',
                                 'transaction_id' => 'TXN-999',
                                 'invoice' => [
                                     'token' => 'sub-token-123',
                                     'total_amount' => 5000
                                 ]
                             ]
                         ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'statut' => 'valide',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('proprietaires', [
            'id' => $proprietaire->id,
            'subscription_status' => 'active',
            'plan' => 'pro',
        ]);
    }

    /** @test */
    public function le_webhook_valide_un_paiement_loyer(): void
    {
        Event::fake([\App\Events\BailSigne::class]);

        $user = User::factory()->locataire()->create();
        $locataire = Locataire::create([
            'user_id' => $user->id,
            'locataire_id' => 'LOC-WH1',
        ]);

        $propUser = User::factory()->proprietaire()->create();
        $proprietaire = Proprietaire::create([
            'user_id' => $propUser->id,
            'proprietaire_id' => 'PROP-WH2',
        ]);

        $region  = \App\Models\Region::create(['nom' => 'Dakar', 'code' => 'DK']);
        $dept    = \App\Models\Departement::create(['nom' => 'Dakar', 'code' => 'DK01', 'region_id' => $region->id]);
        $commune = \App\Models\Commune::create(['nom' => 'Plateau', 'code' => 'PLT', 'departement_id' => $dept->id]);

        $propriete = Propriete::create([
            'proprietaire_id' => $proprietaire->id,
            'titre' => 'Immeuble WH',
            'type' => 'immeuble',
            'region_id' => $region->id,
            'departement_id' => $dept->id,
            'commune_id' => $commune->id,
        ]);

        $logement = Logement::create([
            'propriete_id'          => $propriete->id,
            'numero'                => 'WH1',
            'typelogement'          => 'appartement',
            'nombre_chambres'       => 2,
            'nombre_salles_de_bain' => 1,
            'prix_loyer'            => 150000,
            'statut_occupe'         => 'disponible',
        ]);

        $bail = Bail::create([
            'logement_id'               => $logement->id,
            'locataire_id'              => $locataire->id,
            'proprietaire_id'           => $proprietaire->id,
            'montant_loyer'             => 150000,
            'nombre_mois_caution'       => 2,
            'montant_caution_total'     => 300000,
            'montant_caution_signature' => 300000,
            'jour_echeance'             => 5,
            'date_debut'                => now()->toDateString(),
            'date_fin'                  => now()->addYear()->toDateString(),
            'statut'                    => 'en_attente_paiement',
        ]);

        $paiement = Paiement::create([
            'bail_id' => $bail->id,
            'locataire_id' => $locataire->id,
            'type' => 'signature',
            'montant_attendu' => 300000, // Caution + 1er mois
            'montant_paye' => 0,
            'montant_restant' => 300000,
            'statut' => 'impayé',
            'date_echeance' => now()->toDateString(),
        ]);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'paiement_id' => $paiement->id,
            'type' => 'rent_payment',
            'montant' => 300000,
            'statut' => 'en_attente',
            'paydunyatoken' => 'rent-token-123',
            'payment_gateway' => 'paydunya',
            'mode_paiement' => 'wave',
        ]);

        // Mock BailService to prevent real PDF generation and other heavy stuff
        $this->mock(\App\Services\BailService::class, function ($mock) {
            $mock->shouldReceive('genererLoyersMensuels')->once();
            $mock->shouldReceive('genererEtStockerPdf')->once();
        });

        $response = $this->withHeaders($this->getValidHeaders())
                         ->postJson('/api/webhook/paydunya', [
                             'data' => [
                                 'status' => 'completed',
                                 'transaction_id' => 'TXN-888',
                                 'invoice' => [
                                     'token' => 'rent-token-123',
                                     'total_amount' => 300000
                                 ]
                             ]
                         ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'statut' => 'valide',
        ]);

        $this->assertDatabaseHas('paiements', [
            'id' => $paiement->id,
            'statut' => 'payé',
            'montant_paye' => 300000,
        ]);

        $this->assertDatabaseHas('baux', [
            'id' => $bail->id,
            'statut' => 'actif',
        ]);
        
        $this->assertDatabaseHas('logements', [
            'id' => $logement->id,
            'statut_occupe' => 'occupe',
        ]);
        
        Event::assertDispatched(\App\Events\BailSigne::class);
    }
}
