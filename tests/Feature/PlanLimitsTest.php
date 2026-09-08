<?php

namespace Tests\Feature;

use App\Models\Proprietaire;
use App\Models\Propriete;
use App\Models\User;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer les plans nécessaires pour les tests (reproduit le PlanSeeder)
        Plan::create([
            'slug'             => 'starter',
            'name'             => 'Starter',
            'tier'             => 'starter',
            'billing_cycle'    => 'monthly',
            'price_xof'        => 0, // Gratuit
            'publications_max' => 5,
            'is_active'        => true,
            'features'         => [
                'Gestion des propriétés',
                'Gestion des locataires',
                'Gestion des baux',
                'Paiement des loyers',
                'Tableau de bord basique'
            ],
        ]);

        Plan::create([
            'slug'             => 'pro-monthly',
            'name'             => 'Pro',
            'tier'             => 'pro',
            'billing_cycle'    => 'monthly',
            'price_xof'        => 10000, // 10,000 FCFA par mois
            'publications_max' => 15,
            'is_active'        => true,
            'features'         => [
                'Gestion des propriétés',
                'Gestion des locataires',
                'Gestion des baux',
                'Paiement des loyers',
                'Tableau de bord basique',
                'Mise en avant des logements',
                'Rapports financiers avancés',
                'Export Excel'
            ],
        ]);
    }

    private function createProprietaireStarter(): array
    {
        $user = User::factory()->proprietaire()->create();
        $proprietaire = Proprietaire::factory()->create([
            'user_id'              => $user->id,
            'subscription_status'  => 'active',
            'plan'                 => 'starter',
            'billing_cycle'        => 'monthly',
            'trial_ends_at'        => null,
            'is_actif'             => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        return ['user' => $user, 'proprietaire' => $proprietaire, 'token' => $token];
    }

    private function createProprietairePro(): array
    {
        $user = User::factory()->proprietaire()->create();
        $proprietaire = Proprietaire::factory()->create([
            'user_id'              => $user->id,
            'subscription_status'  => 'active',
            'plan'                 => 'pro',
            'billing_cycle'        => 'monthly',
            'subscription_ends_at' => now()->addMonth(),
            'is_actif'             => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        return ['user' => $user, 'proprietaire' => $proprietaire, 'token' => $token];
    }

    private function createProprietaireProExpired(): array
    {
        $user = User::factory()->proprietaire()->create();
        $proprietaire = Proprietaire::factory()->create([
            'user_id'              => $user->id,
            'subscription_status'  => 'active', // Still active in DB but expired date
            'plan'                 => 'pro',
            'billing_cycle'        => 'monthly',
            'subscription_ends_at' => now()->subDays(1), // Expired yesterday
            'is_actif'             => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        return ['user' => $user, 'proprietaire' => $proprietaire, 'token' => $token];
    }

    private function createPropriete(int $proprietaireId): Propriete
    {
        $region  = \App\Models\Region::create(['nom' => 'Dakar', 'code' => 'DK']);
        $dept    = \App\Models\Departement::create(['nom' => 'Dakar', 'code' => 'DK01', 'region_id' => $region->id]);
        $commune = \App\Models\Commune::create(['nom' => 'Plateau', 'code' => 'PLT', 'departement_id' => $dept->id]);

        return Propriete::create([
            'proprietaire_id' => $proprietaireId,
            'titre'           => 'Immeuble Test',
            'type'            => 'immeuble',
            'region_id'       => $region->id,
            'departement_id'  => $dept->id,
            'commune_id'      => $commune->id,
        ]);
    }

    private function createLogementPayload(int $proprieteId): array
    {
        return [
            'propriete_id'          => $proprieteId,
            'numero'                => 'L' . uniqid(),
            'typelogement'          => 'appartement',
            'nombre_chambres'       => 2,
            'nombre_salles_de_bain' => 1,
            'prix_loyer'            => 150000,
            'meuble'                => false,
            'etat'                  => 'bon',
            'statut_publication'    => 'publie',
            'statut_occupe'         => 'disponible',
        ];
    }

    /** @test */
    public function un_proprietaire_starter_peut_publier_5_logements_mais_pas_6(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietaireStarter();
        $propriete = $this->createPropriete($prop->id);

        // Publication des 5 logements (doit réussir)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->withToken($token)
                ->postJson("/api/proprietaire/proprietes/{$propriete->id}/logements", $this->createLogementPayload($propriete->id));
            $response->assertStatus(201);
        }

        // Publication du 6ème logement (doit échouer)
        $response6 = $this->withToken($token)
            ->postJson("/api/proprietaire/proprietes/{$propriete->id}/logements", $this->createLogementPayload($propriete->id));

        $response6->assertStatus(403)
            ->assertJsonFragment(['code' => 'PUBLISH_LIMIT_REACHED']);
    }

    /** @test */
    public function un_proprietaire_pro_peut_publier_15_logements_mais_pas_16(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietairePro();
        $propriete = $this->createPropriete($prop->id);

        // Publier 15 logements
        for ($i = 0; $i < 15; $i++) {
            $response = $this->withToken($token)
                ->postJson("/api/proprietaire/proprietes/{$propriete->id}/logements", $this->createLogementPayload($propriete->id));
            $response->assertStatus(201);
        }

        // Le 16ème doit échouer
        $response16 = $this->withToken($token)
            ->postJson("/api/proprietaire/proprietes/{$propriete->id}/logements", $this->createLogementPayload($propriete->id));

        $response16->assertStatus(403)
            ->assertJsonFragment(['code' => 'PUBLISH_LIMIT_REACHED']);
    }

    /** @test */
    public function un_proprietaire_pro_expire_est_retrograde_vers_starter(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietaireProExpired();
        $propriete = $this->createPropriete($prop->id);

        // Après expiration du Pro, le propriétaire devrait être rétrogradé vers Starter
        // Donc il devrait pouvoir publier jusqu'à 5 logements

        // Publication de 5 logements (doit réussir car retrogradé vers Starter)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->withToken($token)
                ->postJson("/api/proprietaire/proprietes/{$propriete->id}/logements", $this->createLogementPayload($propriete->id));
            $response->assertStatus(201);
        }

        // Publication du 6ème logement (doit échouer car limite Starter)
        $response6 = $this->withToken($token)
            ->postJson("/api/proprietaire/proprietes/{$propriete->id}/logements", $this->createLogementPayload($propriete->id));

        $response6->assertStatus(403)
            ->assertJsonFragment(['code' => 'PUBLISH_LIMIT_REACHED']);
    }
}