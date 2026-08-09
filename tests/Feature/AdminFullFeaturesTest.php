<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Bail;
use App\Models\Commune;
use App\Models\Demande;
use App\Models\Departement;
use App\Models\Locataire;
use App\Models\Logement;
use App\Models\Plan;
use App\Models\Proprietaire;
use App\Models\Propriete;
use App\Models\Region;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Couverture complète des fonctionnalités admin Luwaas.
 */
class AdminFullFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(NotificationService::class, function ($mock) {
            $mock->shouldReceive('sendToUser')->andReturn(true);
            $mock->shouldReceive('sendToMultipleUsers')->andReturn(1);
        });
    }

    private function createAdmin(): array
    {
        $user = User::factory()->admin()->create(['phone_verified_at' => now()]);

        Admin::create([
            'user_id'   => $user->id,
            'admin_id'  => 'ADM-' . $user->id,
            'username'  => 'admin_' . $user->id,
            'is_active' => true,
        ]);

        return [
            'user'  => $user,
            'token' => $user->createToken('admin-token')->plainTextToken,
        ];
    }

    private function createGeo(): array
    {
        $region = Region::firstOrCreate(['nom' => 'Dakar'], ['code' => 'DK']);
        $dept   = Departement::firstOrCreate(
            ['nom' => 'Dakar', 'region_id' => $region->id],
            ['code' => 'DK01']
        );
        $commune = Commune::firstOrCreate(
            ['nom' => 'Plateau', 'departement_id' => $dept->id],
            ['code' => 'PLT']
        );

        return [$region, $dept, $commune];
    }

    private function createProprietaireEssai(): Proprietaire
    {
        $user = User::factory()->proprietaire()->create(['phone_verified_at' => now()]);

        return Proprietaire::create([
            'user_id'             => $user->id,
            'proprietaire_id'     => 'PROP-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
            'subscription_status' => 'free_trial',
            'plan'                => 'free',
            'trial_ends_at'       => now()->addDays(15),
            'is_actif'            => true,
        ]);
    }

    private function createProprietairePro(): Proprietaire
    {
        $user = User::factory()->proprietaire()->create(['phone_verified_at' => now()]);

        return Proprietaire::create([
            'user_id'              => $user->id,
            'proprietaire_id'      => 'PROP-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
            'subscription_status'  => 'active',
            'plan'                 => 'pro',
            'billing_cycle'        => 'monthly',
            'subscription_ends_at' => now()->addMonth(),
            'is_actif'             => true,
        ]);
    }

    private function createLogement(Proprietaire $proprietaire): Logement
    {
        [$region, $dept, $commune] = $this->createGeo();

        $propriete = Propriete::factory()->create([
            'proprietaire_id' => $proprietaire->id,
            'region_id'       => $region->id,
            'departement_id'  => $dept->id,
            'commune_id'      => $commune->id,
        ]);

        return Logement::factory()->publie()->create([
            'propriete_id' => $propriete->id,
        ]);
    }

    // ══════════════════════════════════════════════
    // DASHBOARD & PRÉSENCE
    // ══════════════════════════════════════════════

    /** @test */
    public function les_stats_comptent_les_bailleurs_en_free_trial(): void
    {
        ['token' => $token] = $this->createAdmin();

        $this->createProprietaireEssai();
        $this->createProprietaireEssai();
        $this->createProprietairePro();

        $response = $this->withToken($token)->getJson('/api/admin/stats');

        $response->assertStatus(200);
        $this->assertSame(2, $response->json('data.abonnements_gratuit'));
        $this->assertSame(1, $response->json('data.abonnements_pro'));
    }

    /** @test */
    public function un_admin_peut_voir_les_utilisateurs_en_ligne(): void
    {
        ['token' => $token] = $this->createAdmin();

        $onlineUser = User::factory()->locataire()->create();
        Cache::put("online_user_{$onlineUser->id}", now()->toDateTimeString(), now()->addMinutes(5));
        Cache::put('online_user_ids', [$onlineUser->id => now()->timestamp], now()->addMinutes(10));

        $response = $this->withToken($token)->getJson('/api/admin/users/online');

        $response->assertStatus(200)
                 ->assertJsonFragment(['success' => true]);

        $onlineIds = collect($response->json('data'))->pluck('id');
        $this->assertTrue($onlineIds->contains($onlineUser->id));
        $this->assertGreaterThanOrEqual(1, $response->json('total_online'));
    }

    // ══════════════════════════════════════════════
    // UTILISATEURS
    // ══════════════════════════════════════════════

    /** @test */
    public function un_admin_peut_usurper_lidentite_dun_bailleur(): void
    {
        ['token' => $token] = $this->createAdmin();
        $proprietaire = $this->createProprietaireEssai();

        $response = $this->withToken($token)
            ->postJson("/api/admin/users/{$proprietaire->user_id}/impersonate");

        $response->assertStatus(200)
                 ->assertJsonFragment(['success' => true])
                 ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    /** @test */
    public function un_admin_ne_peut_pas_usurper_un_autre_admin(): void
    {
        ['token' => $token, 'user' => $adminUser] = $this->createAdmin();

        $this->withToken($token)
             ->postJson("/api/admin/users/{$adminUser->id}/impersonate")
             ->assertStatus(403);
    }

    // ══════════════════════════════════════════════
    // PROPRIÉTAIRES — FILTRES
    // ══════════════════════════════════════════════

    /** @test */
    public function un_admin_peut_filtrer_les_proprietaires_par_statut_trial(): void
    {
        ['token' => $token] = $this->createAdmin();

        $essai = $this->createProprietaireEssai();
        $this->createProprietairePro();

        $response = $this->withToken($token)
            ->getJson('/api/admin/proprietaires?statut=trial');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');

        $this->assertTrue($ids->contains($essai->id));
        $this->assertCount(1, $ids);
    }

    // ══════════════════════════════════════════════
    // SUPERVISION — DÉTAILS
    // ══════════════════════════════════════════════

    /** @test */
    public function un_admin_peut_voir_le_detail_dun_logement(): void
    {
        ['token' => $token] = $this->createAdmin();
        $logement = $this->createLogement($this->createProprietairePro());

        $this->withToken($token)
             ->getJson("/api/admin/logements/{$logement->id}")
             ->assertStatus(200)
             ->assertJsonFragment(['success' => true]);
    }

    /** @test */
    public function un_admin_peut_voir_le_detail_dune_propriete(): void
    {
        ['token' => $token] = $this->createAdmin();
        $proprietaire = $this->createProprietairePro();
        [$region, $dept, $commune] = $this->createGeo();

        $propriete = Propriete::factory()->create([
            'proprietaire_id' => $proprietaire->id,
            'region_id'       => $region->id,
            'departement_id'  => $dept->id,
            'commune_id'      => $commune->id,
        ]);

        $this->withToken($token)
             ->getJson("/api/admin/proprietes/{$propriete->id}")
             ->assertStatus(200)
             ->assertJsonFragment(['success' => true]);
    }

    /** @test */
    public function un_admin_peut_voir_le_detail_dune_demande(): void
    {
        ['token' => $token] = $this->createAdmin();
        $proprietaire = $this->createProprietairePro();
        $logement = $this->createLogement($proprietaire);
        $locataire = Locataire::factory()->create();

        $demande = Demande::factory()->create([
            'logement_id'     => $logement->id,
            'locataire_id'    => $locataire->id,
            'proprietaire_id' => $proprietaire->id,
            'status'          => 'en_attente',
        ]);

        $this->withToken($token)
             ->getJson("/api/admin/demandes/{$demande->id}")
             ->assertStatus(200)
             ->assertJsonFragment(['success' => true]);
    }

    /** @test */
    public function un_admin_peut_voir_le_detail_dun_bail(): void
    {
        ['token' => $token] = $this->createAdmin();
        $proprietaire = $this->createProprietairePro();
        $logement = $this->createLogement($proprietaire);
        $locataire = Locataire::factory()->create();

        $bail = Bail::create([
            'logement_id'               => $logement->id,
            'locataire_id'              => $locataire->id,
            'proprietaire_id'           => $proprietaire->id,
            'montant_loyer'             => 150000,
            'charges_mensuelles'        => 0,
            'nombre_mois_caution'       => 2,
            'montant_caution_total'     => 300000,
            'montant_caution_signature' => 300000,
            'date_debut'                => now()->toDateString(),
            'date_fin'                  => now()->addYear()->toDateString(),
            'statut'                    => 'actif',
        ]);

        $this->withToken($token)
             ->getJson("/api/admin/baux/{$bail->id}")
             ->assertStatus(200)
             ->assertJsonFragment(['success' => true]);
    }

    /** @test */
    public function un_admin_peut_voir_le_detail_dune_transaction(): void
    {
        ['token' => $token] = $this->createAdmin();
        $proprietaire = $this->createProprietairePro();
        $plan = Plan::where('slug', 'pro-monthly')->first();

        $subscription = Subscription::create([
            'proprietaire_id' => $proprietaire->id,
            'plan_id'         => $plan->id,
            'status'          => 'active',
            'amount'          => 5000,
            'starts_at'       => now(),
            'ends_at'         => now()->addMonth(),
        ]);

        $transaction = Transaction::create([
            'type'              => 'subscription_payment',
            'subscription_id'   => $subscription->id,
            'mode_paiement'     => 'wave',
            'montant'           => 5000,
            'statut'            => 'valide',
            'date_transaction'  => now(),
        ]);

        $this->withToken($token)
             ->getJson("/api/admin/transactions/{$transaction->id}")
             ->assertStatus(200)
             ->assertJsonFragment(['success' => true]);
    }

    /** @test */
    public function un_admin_peut_voir_le_resume_financier_des_transactions(): void
    {
        ['token' => $token] = $this->createAdmin();

        Transaction::create([
            'type'             => 'subscription_payment',
            'mode_paiement'    => 'wave',
            'montant'          => 5000,
            'statut'           => 'valide',
            'date_transaction' => now(),
        ]);

        $this->withToken($token)
             ->getJson('/api/admin/transactions/summary')
             ->assertStatus(200)
             ->assertJsonFragment(['success' => true])
             ->assertJsonStructure([
                 'data' => [
                     'total_ce_mois',
                     'nombre_ce_mois',
                     'total_global',
                     'evolution_6_mois',
                 ],
             ]);
    }

    // ══════════════════════════════════════════════
    // ABONNEMENTS & PLANS
    // ══════════════════════════════════════════════

    /** @test */
    public function un_admin_peut_changer_le_plan_dun_proprietaire_mensuel(): void
    {
        ['token' => $token] = $this->createAdmin();
        $proprietaire = $this->createProprietaireEssai();
        $plan = Plan::where('slug', 'pro-monthly')->first();

        $this->withToken($token)
             ->patchJson("/api/admin/subscriptions/{$proprietaire->id}/change-plan", [
                 'plan_id' => $plan->id,
             ])
             ->assertStatus(200)
             ->assertJsonFragment(['success' => true]);

        $proprietaire->refresh();
        $this->assertSame('active', $proprietaire->subscription_status);
        $this->assertSame('pro', $proprietaire->plan);
        $this->assertSame('monthly', $proprietaire->billing_cycle);
        $this->assertTrue($proprietaire->subscription_ends_at->greaterThan(now()->addDays(25)));
    }

    /** @test */
    public function un_admin_peut_changer_le_plan_dun_proprietaire_annuel(): void
    {
        ['token' => $token] = $this->createAdmin();
        $proprietaire = $this->createProprietaireEssai();
        $plan = Plan::where('slug', 'pro-yearly')->first();

        $this->withToken($token)
             ->patchJson("/api/admin/subscriptions/{$proprietaire->id}/change-plan", [
                 'plan_id' => $plan->id,
             ])
             ->assertStatus(200);

        $proprietaire->refresh();
        $this->assertSame('yearly', $proprietaire->billing_cycle);
        $this->assertTrue($proprietaire->subscription_ends_at->greaterThan(now()->addMonths(11)));
    }

    /** @test */
    public function un_admin_peut_annuler_l_abonnement_dun_proprietaire(): void
    {
        ['token' => $token] = $this->createAdmin();
        $proprietaire = $this->createProprietairePro();
        $plan = Plan::where('slug', 'pro-monthly')->first();

        Subscription::create([
            'proprietaire_id' => $proprietaire->id,
            'plan_id'         => $plan->id,
            'status'          => 'active',
            'amount'          => 5000,
            'starts_at'       => now(),
            'ends_at'         => now()->addMonth(),
        ]);

        $this->withToken($token)
             ->patchJson("/api/admin/subscriptions/{$proprietaire->id}/cancel")
             ->assertStatus(200)
             ->assertJsonFragment(['success' => true]);

        $this->assertSame('cancelled', $proprietaire->fresh()->subscription_status);
    }

    /** @test */
    public function un_admin_peut_creer_un_plan_gratuit(): void
    {
        ['token' => $token] = $this->createAdmin();

        $this->withToken($token)
             ->postJson('/api/admin/plans', [
                 'slug'             => 'free-test',
                 'name'             => 'Gratuit Test',
                 'tier'             => 'free',
                 'price_xof'        => 0,
                 'publications_max' => 1,
                 'features'         => ['Essai 15 jours'],
                 'is_active'        => true,
             ])
             ->assertStatus(201)
             ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('plans', [
            'slug'          => 'free-test',
            'billing_cycle' => null,
        ]);
    }

    /** @test */
    public function un_admin_peut_modifier_et_activer_desactiver_un_plan(): void
    {
        ['token' => $token] = $this->createAdmin();
        $plan = Plan::where('slug', 'pro-monthly')->first();

        $this->withToken($token)
             ->putJson("/api/admin/plans/{$plan->id}", [
                 'name'      => 'Pro Modifié',
                 'price_xof' => 6000,
             ])
             ->assertStatus(200)
             ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('plans', [
            'id'        => $plan->id,
            'name'      => 'Pro Modifié',
            'price_xof' => 6000,
        ]);

        $this->withToken($token)
             ->patchJson("/api/admin/plans/{$plan->id}/toggle")
             ->assertStatus(200);

        $this->assertFalse($plan->fresh()->is_active);
    }

    // ══════════════════════════════════════════════
    // EXPORTS CSV
    // ══════════════════════════════════════════════

    /** @test */
    public function un_admin_peut_exporter_les_utilisateurs_en_csv(): void
    {
        ['token' => $token] = $this->createAdmin();
        User::factory()->count(2)->create();

        $response = $this->withToken($token)->get('/api/admin/export/users');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Prénom', $response->streamedContent());
    }

    /** @test */
    public function un_admin_peut_exporter_les_transactions_en_csv(): void
    {
        ['token' => $token] = $this->createAdmin();

        Transaction::create([
            'type'             => 'subscription_payment',
            'mode_paiement'    => 'wave',
            'montant'          => 5000,
            'statut'           => 'valide',
            'date_transaction' => now(),
        ]);

        $response = $this->withToken($token)->get('/api/admin/export/transactions');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Montant (FCFA)', $response->streamedContent());
    }

    // ══════════════════════════════════════════════
    // NOTIFICATIONS BROADCAST
    // ══════════════════════════════════════════════

    /** @test */
    public function un_admin_peut_envoyer_une_notification_broadcast(): void
    {
        ['token' => $token] = $this->createAdmin();
        User::factory()->proprietaire()->count(2)->create(['is_active' => true]);

        $this->withToken($token)
             ->postJson('/api/admin/notifications/broadcast', [
                 'cible'   => 'proprietaires',
                 'titre'   => 'Maintenance',
                 'message' => 'Maintenance ce soir à 22h.',
             ])
             ->assertStatus(200)
             ->assertJsonFragment(['success' => true]);
    }

    // ══════════════════════════════════════════════
    // TICKETS SUPPORT
    // ══════════════════════════════════════════════

    /** @test */
    public function un_admin_peut_gerer_le_cycle_complet_dun_ticket(): void
    {
        ['token' => $token] = $this->createAdmin();
        $user = User::factory()->locataire()->create();

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'sujet'   => 'Problème de paiement',
            'message' => 'Mon paiement Wave est bloqué.',
            'statut'  => 'ouvert',
        ]);

        $this->withToken($token)
             ->getJson('/api/admin/tickets')
             ->assertStatus(200)
             ->assertJsonFragment(['success' => true]);

        $this->withToken($token)
             ->getJson("/api/admin/tickets/{$ticket->id}")
             ->assertStatus(200)
             ->assertJsonPath('data.sujet', 'Problème de paiement');

        $this->withToken($token)
             ->patchJson("/api/admin/tickets/{$ticket->id}/repondre", [
                 'reponse_admin' => 'Nous vérifions avec PayDunya.',
             ])
             ->assertStatus(200)
             ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('tickets', [
            'id'     => $ticket->id,
            'statut' => 'en_cours',
        ]);

        $this->withToken($token)
             ->patchJson("/api/admin/tickets/{$ticket->id}/fermer")
             ->assertStatus(200);

        $ticket->refresh();
        $this->assertSame('ferme', $ticket->statut);
        $this->assertNotNull($ticket->closed_at);
    }
}
