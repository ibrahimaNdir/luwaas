<?php

namespace Tests\Feature;

use App\Models\Logement;
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
            'slug'             => 'free',
            'name'             => 'Gratuit',
            'tier'             => 'free',
            'publications_max' => 1,
            'is_active'        => true,
            'features'         => ['1 annonce active pendant 15 jours'],
        ]);

        Plan::create([
            'slug'             => 'pro-monthly',
            'name'             => 'Pro',
            'tier'             => 'pro',
            'billing_cycle'    => 'monthly',
            'publications_max' => 10,
            'is_active'        => true,
            'features'         => ['10 annonces actives pendant 30 jours'],
        ]);
    }

    private function createProprietaireEssaiGratuit(): array
    {
        $user = User::factory()->proprietaire()->create();

        $proprietaire = Proprietaire::create([
            'user_id'              => $user->id,
            'proprietaire_id'      => 'PROP-TEST-FREE',
            'subscription_status'  => 'free_trial',
            'plan'                 => 'free',
            'trial_ends_at'        => now()->addDays(15),
            'is_actif'             => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        return ['user' => $user, 'proprietaire' => $proprietaire, 'token' => $token];
    }

    private function createProprietaireEssaiExpire(): array
    {
        $user = User::factory()->proprietaire()->create();

        $proprietaire = Proprietaire::create([
            'user_id'              => $user->id,
            'proprietaire_id'      => 'PROP-TEST-EXPIRED',
            'subscription_status'  => 'expired',
            'plan'                 => 'free',
            'trial_ends_at'        => now()->subDays(1),
            'is_actif'             => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        return ['user' => $user, 'proprietaire' => $proprietaire, 'token' => $token];
    }

    private function createProprietairePro(): array
    {
        $user = User::factory()->proprietaire()->create();

        $proprietaire = Proprietaire::create([
            'user_id'              => $user->id,
            'proprietaire_id'      => 'PROP-TEST-PRO',
            'subscription_status'  => 'active',
            'plan'                 => 'pro',
            'billing_cycle'        => 'monthly',
            'subscription_ends_at' => now()->addMonth(),
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
    public function un_proprietaire_gratuit_peut_publier_1_logement_mais_pas_2(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietaireEssaiGratuit();
        $propriete = $this->createPropriete($prop->id);

        // Publication du 1er logement (doit réussir)
        $response1 = $this->withToken($token)
            ->postJson("/api/proprietaire/proprietes/{$propriete->id}/logements", $this->createLogementPayload($propriete->id));
        $response1->assertStatus(201);

        // Publication du 2eme logement (doit échouer)
        $response2 = $this->withToken($token)
            ->postJson("/api/proprietaire/proprietes/{$propriete->id}/logements", $this->createLogementPayload($propriete->id));

        $response2->assertStatus(403)
            ->assertJsonFragment(['code' => 'FREE_TRIAL_PUBLISH_LIMIT_REACHED']);
    }

    /** @test */
    public function un_proprietaire_gratuit_expire_ne_peut_plus_publier(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietaireEssaiExpire();
        $propriete = $this->createPropriete($prop->id);

        $response = $this->withToken($token)
            ->postJson("/api/proprietaire/proprietes/{$propriete->id}/logements", $this->createLogementPayload($propriete->id));

        $response->assertStatus(403)
            ->assertJsonFragment(['code' => 'FREE_TRIAL_EXPIRED']);
    }

    /** @test */
    public function un_proprietaire_pro_peut_publier_10_logements_mais_pas_11(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietairePro();
        $propriete = $this->createPropriete($prop->id);

        // Publier 10 logements
        for ($i = 0; $i < 10; $i++) {
            $response = $this->withToken($token)
                ->postJson("/api/proprietaire/proprietes/{$propriete->id}/logements", $this->createLogementPayload($propriete->id));
            $response->assertStatus(201);
        }

        // Le 11ème doit échouer
        $response11 = $this->withToken($token)
            ->postJson("/api/proprietaire/proprietes/{$propriete->id}/logements", $this->createLogementPayload($propriete->id));

        $response11->assertStatus(403)
            ->assertJsonFragment(['code' => 'PUBLISH_LIMIT_REACHED']);
    }
}
