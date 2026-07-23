<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Locataire;
use App\Models\Proprietaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests des routes Admin — stats, gestion des propriétaires,
 * utilisateurs, abonnements, contrôle des accès
 */
class AdminTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /**
     * Crée un admin complet et retourne son token.
     */
    private function createAdmin(): array
    {
        $user = User::factory()->admin()->create(['phone_verified_at' => now()]);

        Admin::create([
            'user_id'   => $user->id,
            'admin_id'  => 'ADM-' . $user->id,
            'username'  => 'admin_' . $user->id,
            'is_active' => true,
        ]);

        $token = $user->createToken('admin-token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    private function createProprietaireUser(): User
    {
        $user = User::factory()->proprietaire()->create(['phone_verified_at' => now()]);

        Proprietaire::create([
            'user_id'             => $user->id,
            'proprietaire_id'     => 'PROP-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
            'subscription_status' => 'active',
            'plan'                => 'starter',
            'billing_cycle'       => 'monthly',
            'subscription_ends_at'=> now()->addMonth(),
            'is_actif'            => true,
        ]);

        return $user;
    }

    // ══════════════════════════════════════════════
    // CONTRÔLE D'ACCÈS
    // ══════════════════════════════════════════════

    /** @test */
    public function un_non_admin_ne_peut_pas_acceder_aux_routes_admin(): void
    {
        $user  = User::factory()->proprietaire()->create(['phone_verified_at' => now()]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
             ->getJson('/api/admin/stats')
             ->assertStatus(403);
    }

    /** @test */
    public function un_invité_ne_peut_pas_acceder_aux_routes_admin(): void
    {
        $this->getJson('/api/admin/stats')
             ->assertStatus(401);
    }

    /** @test */
    public function un_locataire_ne_peut_pas_acceder_aux_routes_admin(): void
    {
        $user = User::factory()->locataire()->create(['phone_verified_at' => now()]);
        Locataire::create([
            'user_id'      => $user->id,
            'locataire_id' => 'LOC-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
             ->getJson('/api/admin/stats')
             ->assertStatus(403);
    }

    // ══════════════════════════════════════════════
    // STATS GLOBALES
    // ══════════════════════════════════════════════

    /** @test */
    public function un_admin_peut_voir_les_stats_globales(): void
    {
        ['token' => $token] = $this->createAdmin();

        $response = $this->withToken($token)
                         ->getJson('/api/admin/stats');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'mrr',
                         'total_proprietaires',
                         'proprietaires_actifs',
                         'total_locataires',
                         'paiements_en_attente',
                         'paiements_en_retard',
                         'genere_le',
                     ],
                 ])
                 ->assertJsonFragment(['success' => true]);
    }

    /** @test */
    public function les_stats_refletent_les_donnees_de_la_base(): void
    {
        ['token' => $token] = $this->createAdmin();

        // Créer quelques propriétaires et locataires
        $this->createProprietaireUser();
        $this->createProprietaireUser();

        $locUser = User::factory()->locataire()->create();
        Locataire::create([
            'user_id'      => $locUser->id,
            'locataire_id' => 'LOC-' . str_pad($locUser->id, 5, '0', STR_PAD_LEFT),
        ]);

        $response = $this->withToken($token)->getJson('/api/admin/stats');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertGreaterThanOrEqual(2, $data['total_proprietaires']);
        $this->assertGreaterThanOrEqual(1, $data['total_locataires']);
    }

    // ══════════════════════════════════════════════
    // GESTION DES PROPRIÉTAIRES
    // ══════════════════════════════════════════════

    /** @test */
    public function un_admin_peut_lister_les_proprietaires(): void
    {
        ['token' => $token] = $this->createAdmin();
        $this->createProprietaireUser();

        $response = $this->withToken($token)
                         ->getJson('/api/admin/proprietaires');

        $response->assertStatus(200)
                 ->assertJsonFragment(['success' => true])
                 ->assertJsonStructure(['data']);
    }

    /** @test */
    public function un_admin_peut_voir_le_detail_dun_proprietaire(): void
    {
        ['token' => $token] = $this->createAdmin();
        $proprietaireUser   = $this->createProprietaireUser();
        $proprietaire       = $proprietaireUser->proprietaire;

        $response = $this->withToken($token)
                         ->getJson("/api/admin/proprietaires/{$proprietaire->id}");

        $response->assertStatus(200)
                 ->assertJsonFragment(['success' => true])
                 ->assertJsonStructure([
                     'data' => ['id', 'user_id', 'subscription_status'],
                     'stats',
                 ]);
    }

    /** @test */
    public function un_admin_peut_activer_un_proprietaire(): void
    {
        ['token' => $token] = $this->createAdmin();

        $user = User::factory()->proprietaire()->create();
        $proprietaire = Proprietaire::create([
            'user_id'             => $user->id,
            'proprietaire_id'     => 'PROP-99999',
            'is_actif'            => false,
            'subscription_status' => 'expired',
        ]);

        $response = $this->withToken($token)
                         ->patchJson("/api/admin/proprietaires/{$proprietaire->id}/activate");

        $response->assertStatus(200)
                 ->assertJsonFragment(['success' => true]);

        $this->assertTrue($proprietaire->fresh()->is_actif);
    }

    /** @test */
    public function un_admin_peut_suspendre_un_proprietaire(): void
    {
        ['token' => $token] = $this->createAdmin();
        $proprietaireUser   = $this->createProprietaireUser();
        $proprietaire       = $proprietaireUser->proprietaire;

        $response = $this->withToken($token)
                         ->patchJson("/api/admin/proprietaires/{$proprietaire->id}/suspend");

        $response->assertStatus(200)
                 ->assertJsonFragment(['success' => true]);

        $this->assertFalse($proprietaire->fresh()->is_actif);
    }

    /** @test */
    public function ladmin_retourne_404_pour_un_proprietaire_inexistant(): void
    {
        ['token' => $token] = $this->createAdmin();

        $this->withToken($token)
             ->getJson('/api/admin/proprietaires/99999')
             ->assertStatus(404);
    }

    // ══════════════════════════════════════════════
    // GESTION DES UTILISATEURS
    // ══════════════════════════════════════════════

    /** @test */
    public function un_admin_peut_lister_tous_les_utilisateurs(): void
    {
        ['token' => $token] = $this->createAdmin();
        User::factory()->count(3)->create();

        $response = $this->withToken($token)
                         ->getJson('/api/admin/users');

        $response->assertStatus(200);
    }

    /** @test */
    public function un_admin_peut_voir_le_detail_dun_utilisateur(): void
    {
        ['token' => $token] = $this->createAdmin();
        $user = User::factory()->create();

        $response = $this->withToken($token)
                         ->getJson("/api/admin/users/{$user->id}");

        $response->assertStatus(200);
    }

    // ══════════════════════════════════════════════
    // GESTION DES ABONNEMENTS
    // ══════════════════════════════════════════════

    /** @test */
    public function un_admin_peut_lister_les_abonnements(): void
    {
        ['token' => $token] = $this->createAdmin();

        $response = $this->withToken($token)
                         ->getJson('/api/admin/subscriptions');

        $response->assertStatus(200);
    }

    /** @test */
    public function un_admin_peut_voir_les_plans_disponibles(): void
    {
        ['token' => $token] = $this->createAdmin();

        $response = $this->withToken($token)
                         ->getJson('/api/admin/plans');

        $response->assertStatus(200);
    }

    // ══════════════════════════════════════════════
    // DONNÉES DE L'APPLICATION
    // ══════════════════════════════════════════════

    /** @test */
    public function un_admin_peut_voir_tous_les_logements(): void
    {
        ['token' => $token] = $this->createAdmin();

        $response = $this->withToken($token)
                         ->getJson('/api/admin/logements');

        $response->assertStatus(200);
    }

    /** @test */
    public function un_admin_peut_voir_toutes_les_proprietes(): void
    {
        ['token' => $token] = $this->createAdmin();

        $response = $this->withToken($token)
                         ->getJson('/api/admin/proprietes');

        $response->assertStatus(200);
    }

    /** @test */
    public function un_admin_peut_voir_tous_les_baux(): void
    {
        ['token' => $token] = $this->createAdmin();

        $response = $this->withToken($token)
                         ->getJson('/api/admin/baux');

        $response->assertStatus(200);
    }

    /** @test */
    public function un_admin_peut_voir_toutes_les_demandes(): void
    {
        ['token' => $token] = $this->createAdmin();

        $response = $this->withToken($token)
                         ->getJson('/api/admin/demandes');

        $response->assertStatus(200);
    }

    /** @test */
    public function un_admin_peut_voir_tous_les_paiements(): void
    {
        ['token' => $token] = $this->createAdmin();

        $response = $this->withToken($token)
                         ->getJson('/api/admin/paiements');

        $response->assertStatus(200);
    }

    /** @test */
    public function un_admin_peut_voir_toutes_les_transactions(): void
    {
        ['token' => $token] = $this->createAdmin();

        $response = $this->withToken($token)
                         ->getJson('/api/admin/transactions');

        $response->assertStatus(200);
    }
}
