<?php

namespace Tests\Feature;

use App\Models\Demande;
use App\Models\Locataire;
use App\Models\Logement;
use App\Models\Proprietaire;
use App\Models\Propriete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests du backoffice Locataire — gestion des demandes, baux, paiements
 */
class LocataireTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    private function createLocataire(): array
    {
        $user = User::factory()->locataire()->create(['phone_verified_at' => now()]);

        $locataire = Locataire::create([
            'user_id'      => $user->id,
            'locataire_id' => 'LOC-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
        ]);

        $token = $user->createToken('locataire-token')->plainTextToken;

        return ['user' => $user, 'locataire' => $locataire, 'token' => $token];
    }

    private function createProprietaireEtLogement(): array
    {
        $userProp = User::factory()->proprietaire()->create();
        $prop = Proprietaire::create([
            'user_id'             => $userProp->id,
            'proprietaire_id'     => 'PROP-11111',
            'subscription_status' => 'active',
        ]);

        $region     = \App\Models\Region::create(['nom' => 'Dakar', 'code' => 'DK']);
        $dept       = \App\Models\Departement::create(['nom' => 'Dakar', 'code' => 'DK01', 'region_id' => $region->id]);
        $commune    = \App\Models\Commune::create(['nom' => 'Plateau', 'code' => 'PLT', 'departement_id' => $dept->id]);

        $propriete = Propriete::create([
            'proprietaire_id' => $prop->id,
            'titre'           => 'Immeuble Locataire Test',
            'type'            => 'immeuble',
            'region_id'       => $region->id,
            'departement_id'  => $dept->id,
            'commune_id'      => $commune->id,
        ]);

        $logement = Logement::create([
            'propriete_id'       => $propriete->id,
            'numero'             => 'A1',
            'typelogement'       => 'appartement',
            'nombre_chambres'    => 2,
            'nombre_salles_de_bain' => 1,
            'prix_loyer'         => 150000,
            'statut_publication' => 'publie',
            'statut_occupe'      => 'disponible',
        ]);

        return ['proprietaire' => $prop, 'propriete' => $propriete, 'logement' => $logement];
    }

    private function createBailEtPaiement($locataire, $propAndLogement): array
    {
        $bail = \App\Models\Bail::create([
            'logement_id'                => $propAndLogement['logement']->id,
            'locataire_id'               => $locataire->id,
            'proprietaire_id'            => $propAndLogement['proprietaire']->id,
            'montant_loyer'              => 150000,
            'nombre_mois_caution'        => 2,
            'montant_caution_total'      => 300000,
            'montant_caution_signature'  => 300000,
            'jour_echeance'              => 5,
            'renouvellement_automatique' => false,
            'date_debut'                 => now()->toDateString(),
            'date_fin'                   => now()->addYear()->toDateString(),
            'statut'                     => 'actif',
        ]);

        $paiement = \App\Models\Paiement::create([
            'bail_id'         => $bail->id,
            'locataire_id'    => $locataire->id,
            'type'            => 'loyer_mensuel',
            'periode'         => now()->format('F Y'),
            'montant_attendu' => 150000,
            'montant_paye'    => 0,
            'montant_restant' => 150000,
            'statut'          => 'impayé',
            'date_echeance'   => now()->addDays(5)->toDateString(),
        ]);

        return ['bail' => $bail, 'paiement' => $paiement];
    }

    // ══════════════════════════════════════════════
    // CONTRÔLE D'ACCÈS
    // ══════════════════════════════════════════════

    /** @test */
    public function un_proprietaire_ne_peut_pas_acceder_aux_routes_locataire(): void
    {
        $user = User::factory()->proprietaire()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
             ->getJson('/api/locataire/dashboard')
             ->assertStatus(403);
    }

    /** @test */
    public function un_invite_ne_peut_pas_acceder_aux_routes_locataire(): void
    {
        $this->getJson('/api/locataire/dashboard')
             ->assertStatus(401);
    }

    // ══════════════════════════════════════════════
    // DASHBOARD
    // ══════════════════════════════════════════════

    /** @test */
    public function un_locataire_peut_acceder_a_son_dashboard(): void
    {
        ['token' => $token] = $this->createLocataire();

        $this->withToken($token)
             ->getJson('/api/locataire/dashboard')
             ->assertStatus(200);
    }

    // ══════════════════════════════════════════════
    // DEMANDES DE LOCATION
    // ══════════════════════════════════════════════

    /** @test */
    public function un_locataire_peut_creer_une_demande_de_location(): void
    {
        ['token' => $token] = $this->createLocataire();
        $data = $this->createProprietaireEtLogement();
        $logement = $data['logement'];

        $response = $this->withToken($token)->postJson('/api/locataire/demandes', [
            'logement_id' => $logement->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('demandes', [
            'logement_id' => $logement->id,
            'status'      => 'en_attente',
        ]);
    }

    /** @test */
    public function un_locataire_peut_lister_ses_demandes(): void
    {
        ['token' => $token, 'locataire' => $locataire] = $this->createLocataire();
        $data = $this->createProprietaireEtLogement();
        
        Demande::create([
            'logement_id'     => $data['logement']->id,
            'locataire_id'    => $locataire->id,
            'proprietaire_id' => $data['proprietaire']->id,
            'status'          => 'en_attente',
            'date_demande'    => now(),
        ]);

        $response = $this->withToken($token)
                         ->getJson('/api/locataire/demandes');

        $response->assertStatus(200);
    }

    /** @test */
    public function un_locataire_peut_annuler_sa_demande(): void
    {
        ['token' => $token, 'locataire' => $locataire] = $this->createLocataire();
        $data = $this->createProprietaireEtLogement();
        
        $demande = Demande::create([
            'logement_id'     => $data['logement']->id,
            'locataire_id'    => $locataire->id,
            'proprietaire_id' => $data['proprietaire']->id,
            'status'          => 'en_attente',
            'date_demande'    => now(),
        ]);

        $response = $this->withToken($token)
                         ->patchJson("/api/locataire/demandes/{$demande->id}/annuler");

        $response->assertStatus(200);
        $this->assertDatabaseHas('demandes', [
            'id'     => $demande->id,
            'status' => 'annulee',
        ]);
    }

    // ══════════════════════════════════════════════
    // PAIEMENTS
    // ══════════════════════════════════════════════

    /** @test */
    public function un_locataire_peut_lister_ses_paiements(): void
    {
        ['token' => $token, 'locataire' => $locataire] = $this->createLocataire();
        $data = $this->createProprietaireEtLogement();
        $this->createBailEtPaiement($locataire, $data);

        $response = $this->withToken($token)
                         ->getJson('/api/locataire/paiements');

        $response->assertStatus(200);
    }

    /** @test */
    public function un_locataire_peut_initier_un_paiement_de_loyer(): void
    {
        ['token' => $token, 'locataire' => $locataire] = $this->createLocataire();
        $data = $this->createProprietaireEtLogement();
        $bp = $this->createBailEtPaiement($locataire, $data);

        $this->mock(\App\Services\PaymentService::class, function ($mock) {
            $mock->shouldReceive('validerInitiationLoyer')->andReturn(null);
            $mock->shouldReceive('initierLoyer')->andReturn([
                (object)[
                    'id'            => 99,
                    'type'          => 'loyer',
                    'reference'     => 'REF-99',
                    'paydunyaToken' => 'token-test',
                    'montant'       => 150000,
                    'mode_paiement' => 'wave',
                    'statut'        => 'pending'
                ],
                ['payment_url' => 'http://paydunya.test']
            ]);
        });

        $response = $this->withToken($token)->postJson("/api/locataire/paiements/{$bp['paiement']->id}/initier", [
            'operateur' => 'wave',
            'telephone' => '771234567',
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['success' => true]);
    }
}
