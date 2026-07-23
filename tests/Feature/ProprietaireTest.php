<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Bail;
use App\Models\Demande;
use App\Models\Locataire;
use App\Models\Logement;
use App\Models\Paiement;
use App\Models\Proprietaire;
use App\Models\Propriete;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests du backoffice Proprietaire (Propriétaire) :
 * accès, dashboard, propriétés, logements, demandes, baux,
 * locataires, abonnements, paiements.
 */
class ProprietaireTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /**
     * Crée un proprietaire avec un abonnement actif et retourne user/proprietaire/token.
     */
    private function createProprietaireAbonne(): array
    {
        $user = User::factory()->proprietaire()->create(['phone_verified_at' => now()]);

        $proprietaire = Proprietaire::create([
            'user_id'              => $user->id,
            'proprietaire_id'      => 'PROP-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
            'subscription_status'  => 'active',
            'plan'                 => 'starter',
            'billing_cycle'        => 'monthly',
            'subscription_ends_at' => now()->addMonth(),
            'trial_ends_at'        => null,
            'is_actif'             => true,
        ]);

        $token = $user->createToken('proprietaire-token')->plainTextToken;

        return ['user' => $user, 'proprietaire' => $proprietaire, 'token' => $token];
    }

    /**
     * Crée un proprietaire en période d'essai gratuit.
     */
    private function createProprietaireEssai(): array
    {
        $user = User::factory()->proprietaire()->create(['phone_verified_at' => now()]);

        $proprietaire = Proprietaire::create([
            'user_id'              => $user->id,
            'proprietaire_id'      => 'PROP-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
            'subscription_status'  => 'free_trial',
            'plan'                 => 'free',
            'trial_ends_at'        => now()->addDays(15),
            'subscription_ends_at' => null,
            'is_actif'             => true,
        ]);

        $token = $user->createToken('proprietaire-token')->plainTextToken;

        return ['user' => $user, 'proprietaire' => $proprietaire, 'token' => $token];
    }

    /**
     * Crée un proprietaire sans abonnement actif (expiré).
     */
    private function createProprietaireSansAbonnement(): array
    {
        $user = User::factory()->proprietaire()->create(['phone_verified_at' => now()]);

        $proprietaire = Proprietaire::create([
            'user_id'              => $user->id,
            'proprietaire_id'      => 'PROP-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
            'subscription_status'  => 'expired',
            'plan'                 => 'free',
            'trial_ends_at'        => now()->subDays(5),
            'subscription_ends_at' => now()->subDays(5),
            'is_actif'             => true,
        ]);

        $token = $user->createToken('proprietaire-token')->plainTextToken;

        return ['user' => $user, 'proprietaire' => $proprietaire, 'token' => $token];
    }

    /**
     * Crée les données géographiques nécessaires (région → département → commune).
     */
    private function createGeoData(): array
    {
        $region  = \App\Models\Region::create(['nom' => 'Dakar', 'code' => 'DK']);
        $dept    = \App\Models\Departement::create(['nom' => 'Dakar', 'code' => 'DK01', 'region_id' => $region->id]);
        $commune = \App\Models\Commune::create(['nom' => 'Plateau', 'code' => 'PLT', 'departement_id' => $dept->id]);

        return ['region' => $region, 'departement' => $dept, 'commune' => $commune];
    }

    /**
     * Crée une propriété pour un proprietaire avec les données geo.
     */
    private function createPropriete(int $proprietaireId, array $geo): Propriete
    {
        return Propriete::create([
            'proprietaire_id' => $proprietaireId,
            'titre'           => 'Immeuble Test',
            'type'            => 'immeuble',
            'adresse'         => '12 Rue Test Dakar',
            'region_id'       => $geo['region']->id,
            'departement_id'  => $geo['departement']->id,
            'commune_id'      => $geo['commune']->id,
        ]);
    }

    /**
     * Crée un logement publié dans une propriété.
     */
    private function createLogementPublie(int $proprieteId): Logement
    {
        return Logement::create([
            'propriete_id'          => $proprieteId,
            'numero'                => 'L' . uniqid(),
            'typelogement'          => 'appartement',
            'nombre_chambres'       => 2,
            'nombre_salles_de_bain' => 1,
            'prix_loyer'            => 150000,
            'statut_publication'    => 'publie',
            'statut_occupe'         => 'disponible',
        ]);
    }

    /**
     * Crée un locataire indépendant.
     */
    private function createLocataire(): Locataire
    {
        $locUser = User::factory()->locataire()->create(['phone_verified_at' => now()]);

        return Locataire::create([
            'user_id'      => $locUser->id,
            'locataire_id' => 'LOC-' . str_pad($locUser->id, 5, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * Crée une demande en attente liée à un logement et un locataire.
     */
    private function createDemande(int $logementId, int $locataireId, int $proprietaireId): Demande
    {
        return Demande::create([
            'logement_id'     => $logementId,
            'locataire_id'    => $locataireId,
            'proprietaire_id' => $proprietaireId,
            'status'          => 'en_attente',
            'date_demande'    => now(),
        ]);
    }

    // ══════════════════════════════════════════════
    // CONTRÔLE D'ACCÈS
    // ══════════════════════════════════════════════

    /** @test */
    public function un_invite_ne_peut_pas_acceder_aux_routes_proprietaire(): void
    {
        $this->getJson('/api/proprietaire/dashboard')
             ->assertStatus(401);
    }

    /** @test */
    public function un_locataire_ne_peut_pas_acceder_aux_routes_proprietaire(): void
    {
        $user = User::factory()->locataire()->create(['phone_verified_at' => now()]);
        Locataire::create([
            'user_id'      => $user->id,
            'locataire_id' => 'LOC-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
             ->getJson('/api/proprietaire/dashboard')
             ->assertStatus(403);
    }

    /** @test */
    public function un_admin_ne_peut_pas_acceder_aux_routes_proprietaire(): void
    {
        $user = User::factory()->admin()->create(['phone_verified_at' => now()]);
        Admin::create([
            'user_id'   => $user->id,
            'admin_id'  => 'ADM-' . $user->id,
            'username'  => 'admin_' . $user->id,
            'is_active' => true,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
             ->getJson('/api/proprietaire/dashboard')
             ->assertStatus(403);
    }

    /** @test */
    public function un_proprietaire_sans_abonnement_peut_acceder_au_backoffice(): void
    {
        ['token' => $token] = $this->createProprietaireSansAbonnement();

        $this->withToken($token)
             ->getJson('/api/proprietaire/dashboard')
             ->assertStatus(200);
    }

    // ══════════════════════════════════════════════
    // DASHBOARD
    // ══════════════════════════════════════════════

    /** @test */
    public function un_proprietaire_abonne_peut_acceder_au_dashboard(): void
    {
        ['token' => $token] = $this->createProprietaireAbonne();

        $this->withToken($token)
             ->getJson('/api/proprietaire/dashboard')
             ->assertStatus(200);
    }

    /** @test */
    public function un_proprietaire_en_essai_peut_acceder_au_dashboard(): void
    {
        ['token' => $token] = $this->createProprietaireEssai();

        $this->withToken($token)
             ->getJson('/api/proprietaire/dashboard')
             ->assertStatus(200);
    }

    // ══════════════════════════════════════════════
    // ABONNEMENTS
    // ══════════════════════════════════════════════

    /** @test */
    public function un_proprietaire_peut_voir_son_statut_dabonnement(): void
    {
        ['token' => $token] = $this->createProprietaireAbonne();

        $this->withToken($token)
             ->getJson('/api/abonnements/statut')
             ->assertStatus(200);
    }

    /** @test */
    public function un_proprietaire_sans_abonnement_peut_voir_son_statut(): void
    {
        ['token' => $token] = $this->createProprietaireSansAbonnement();

        $this->withToken($token)
             ->getJson('/api/abonnements/statut')
             ->assertStatus(200);
    }

    /** @test */
    public function un_proprietaire_peut_voir_les_plans_disponibles(): void
    {
        ['token' => $token] = $this->createProprietaireAbonne();

        $this->withToken($token)
             ->getJson('/api/plans')
             ->assertStatus(200);
    }

    // ══════════════════════════════════════════════
    // GÉOLOCALISATION
    // ══════════════════════════════════════════════

    /** @test */
    public function un_proprietaire_peut_consulter_les_regions(): void
    {
        ['token' => $token] = $this->createProprietaireAbonne();
        $this->createGeoData();

        $this->withToken($token)
             ->getJson('/api/proprietaire/regions')
             ->assertStatus(200);
    }

    /** @test */
    public function un_proprietaire_peut_consulter_les_departements_dune_region(): void
    {
        ['token' => $token] = $this->createProprietaireAbonne();
        $geo = $this->createGeoData();

        $this->withToken($token)
             ->getJson("/api/proprietaire/regions/{$geo['region']->id}/departements")
             ->assertStatus(200);
    }

    /** @test */
    public function un_proprietaire_peut_consulter_les_communes_dun_departement(): void
    {
        ['token' => $token] = $this->createProprietaireAbonne();
        $geo = $this->createGeoData();

        $this->withToken($token)
             ->getJson("/api/proprietaire/departements/{$geo['departement']->id}/communes")
             ->assertStatus(200);
    }

    // ══════════════════════════════════════════════
    // GESTION DES PROPRIÉTÉS
    // ══════════════════════════════════════════════

    /** @test */
    public function un_proprietaire_peut_lister_ses_proprietes(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietaireAbonne();
        $geo = $this->createGeoData();
        $this->createPropriete($prop->id, $geo);

        $this->withToken($token)
             ->getJson('/api/proprietaire/proprietes')
             ->assertStatus(200);
    }

    /** @test */
    public function un_proprietaire_peut_creer_une_propriete(): void
    {
        ['token' => $token] = $this->createProprietaireAbonne();
        $geo = $this->createGeoData();

        $response = $this->withToken($token)->postJson('/api/proprietaire/proprietes', [
            'titre'          => 'Nouvelle Villa Test',
            'type'           => 'villa',
            'adresse'        => '1 Rue Faidherbe Dakar',
            'description'    => 'Belle villa avec jardin',
            'region_id'      => $geo['region']->id,
            'departement_id' => $geo['departement']->id,
            'commune_id'     => $geo['commune']->id,
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['message' => 'Propriété ajoutée avec succès.']);
    }

    /** @test */
    public function la_creation_de_propriete_echoue_sans_les_champs_requis(): void
    {
        ['token' => $token] = $this->createProprietaireAbonne();

        $this->withToken($token)
             ->postJson('/api/proprietaire/proprietes', [])
             ->assertStatus(422);
    }

    /** @test */
    public function un_proprietaire_peut_voir_le_detail_dune_de_ses_proprietes(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietaireAbonne();
        $geo = $this->createGeoData();
        $propriete = $this->createPropriete($prop->id, $geo);

        $this->withToken($token)
             ->getJson("/api/proprietaire/proprietes/{$propriete->id}")
             ->assertStatus(200);
    }

    /** @test */
    public function un_proprietaire_obtient_404_pour_une_propriete_inexistante(): void
    {
        ['token' => $token] = $this->createProprietaireAbonne();

        $this->withToken($token)
             ->getJson('/api/proprietaire/proprietes/99999')
             ->assertStatus(404);
    }

    /** @test */
    public function un_proprietaire_peut_modifier_sa_propriete(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietaireAbonne();
        $geo = $this->createGeoData();
        $propriete = $this->createPropriete($prop->id, $geo);

        $response = $this->withToken($token)->putJson("/api/proprietaire/proprietes/{$propriete->id}", [
            'titre'          => 'Titre modifié',
            'type'           => 'maison',
            'region_id'      => $geo['region']->id,
            'departement_id' => $geo['departement']->id,
            'commune_id'     => $geo['commune']->id,
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['message' => 'Propriété mise à jour avec succès.']);

        $this->assertDatabaseHas('proprietes', ['id' => $propriete->id, 'titre' => 'Titre modifié']);
    }

    /** @test */
    public function un_proprietaire_peut_supprimer_sa_propriete(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietaireAbonne();
        $geo = $this->createGeoData();
        $propriete = $this->createPropriete($prop->id, $geo);

        $this->withToken($token)
             ->deleteJson("/api/proprietaire/proprietes/{$propriete->id}")
             ->assertStatus(204);

        $this->assertDatabaseMissing('proprietes', ['id' => $propriete->id]);
    }

    /** @test */
    public function un_proprietaire_ne_peut_pas_supprimer_la_propriete_dun_autre(): void
    {
        ['token' => $token] = $this->createProprietaireAbonne();
        $geo = $this->createGeoData();

        $autreUser = User::factory()->proprietaire()->create();
        $autreProp = Proprietaire::create([
            'user_id'             => $autreUser->id,
            'proprietaire_id'     => 'PROP-88888',
            'subscription_status' => 'active',
        ]);
        $propriete = $this->createPropriete($autreProp->id, $geo);

        $this->withToken($token)
             ->deleteJson("/api/proprietaire/proprietes/{$propriete->id}")
             ->assertStatus(404);
    }

    // ══════════════════════════════════════════════
    // GESTION DES LOGEMENTS
    // ══════════════════════════════════════════════

    /** @test */
    public function un_proprietaire_peut_lister_tous_ses_logements(): void
    {
        ['token' => $token] = $this->createProprietaireAbonne();

        $this->withToken($token)
             ->getJson('/api/proprietaire/mes-logements')
             ->assertStatus(200);
    }

    /** @test */
    public function un_proprietaire_peut_lister_les_logements_dune_propriete(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietaireAbonne();
        $geo = $this->createGeoData();
        $propriete = $this->createPropriete($prop->id, $geo);

        $this->withToken($token)
             ->getJson("/api/proprietaire/proprietes/{$propriete->id}/logements")
             ->assertStatus(200);
    }

    /** @test */
    public function un_proprietaire_peut_ajouter_un_logement_a_sa_propriete(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietaireAbonne();
        $geo = $this->createGeoData();
        $propriete = $this->createPropriete($prop->id, $geo);

        $response = $this->withToken($token)->postJson(
            "/api/proprietaire/proprietes/{$propriete->id}/logements",
            [
                'numero'                => 'A1',
                'typelogement'          => 'appartement',
                'superficie'            => 60,
                'nombre_chambres'       => 2,
                'nombre_salles_de_bain' => 1,
                'meuble'                => false,
                'etat'                  => 'bon',
                'prix_loyer'            => 120000,
            ]
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('logements', ['numero' => 'A1', 'prix_loyer' => 120000]);
    }

    /** @test */
    public function la_creation_de_logement_echoue_sans_les_champs_requis(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietaireAbonne();
        $geo = $this->createGeoData();
        $propriete = $this->createPropriete($prop->id, $geo);

        $this->withToken($token)
             ->postJson("/api/proprietaire/proprietes/{$propriete->id}/logements", [])
             ->assertStatus(422);
    }

    /** @test */
    public function un_proprietaire_peut_modifier_les_infos_dun_logement(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietaireAbonne();
        $geo = $this->createGeoData();
        $propriete = $this->createPropriete($prop->id, $geo);
        $logement  = $this->createLogementPublie($propriete->id);

        $response = $this->withToken($token)->putJson(
            "/api/proprietaire/proprietes/{$propriete->id}/logements/{$logement->id}",
            ['prix_loyer' => 200000, 'etat' => 'bon']
        );

        $response->assertStatus(200)
                 ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('logements', ['id' => $logement->id, 'prix_loyer' => 200000]);
    }

    /** @test */
    public function un_proprietaire_peut_supprimer_un_logement_de_sa_propriete(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietaireAbonne();
        $geo = $this->createGeoData();
        $propriete = $this->createPropriete($prop->id, $geo);
        $logement  = $this->createLogementPublie($propriete->id);

        $this->withToken($token)
             ->deleteJson("/api/proprietaire/proprietes/{$propriete->id}/logements/{$logement->id}")
             ->assertStatus(204);

        $this->assertDatabaseMissing('logements', ['id' => $logement->id]);
    }

    /** @test */
    public function un_proprietaire_peut_voir_le_detail_dun_logement(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietaireAbonne();
        $geo = $this->createGeoData();
        $propriete = $this->createPropriete($prop->id, $geo);
        $logement  = $this->createLogementPublie($propriete->id);

        $this->withToken($token)
             ->getJson("/api/proprietaire/mes-logements/{$logement->id}")
             ->assertStatus(200);
    }

    // ══════════════════════════════════════════════
    // GESTION DES DEMANDES
    // ══════════════════════════════════════════════

    /** @test */
    public function un_proprietaire_peut_lister_ses_demandes_de_location(): void
    {
        ['token' => $token] = $this->createProprietaireAbonne();

        $this->withToken($token)
             ->getJson('/api/proprietaire/demandes')
             ->assertStatus(200);
    }

    /** @test */
    public function un_proprietaire_peut_accepter_une_demande_en_attente(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietaireAbonne();
        $geo       = $this->createGeoData();
        $propriete = $this->createPropriete($prop->id, $geo);
        $logement  = $this->createLogementPublie($propriete->id);
        $locataire = $this->createLocataire();
        $demande   = $this->createDemande($logement->id, $locataire->id, $prop->id);

        $response = $this->withToken($token)
                         ->patchJson("/api/proprietaire/demandes/{$demande->id}/accepter");

        $response->assertStatus(200)
                 ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('demandes', ['id' => $demande->id, 'status' => 'acceptee']);
    }

    /** @test */
    public function un_proprietaire_peut_refuser_une_demande_en_attente(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietaireAbonne();
        $geo       = $this->createGeoData();
        $propriete = $this->createPropriete($prop->id, $geo);
        $logement  = $this->createLogementPublie($propriete->id);
        $locataire = $this->createLocataire();
        $demande   = $this->createDemande($logement->id, $locataire->id, $prop->id);

        $response = $this->withToken($token)
                         ->patchJson("/api/proprietaire/demandes/{$demande->id}/refuser");

        $response->assertStatus(200)
                 ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('demandes', ['id' => $demande->id, 'status' => 'refusee']);
    }

    /** @test */
    public function un_proprietaire_ne_peut_pas_accepter_une_demande_deja_refusee(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietaireAbonne();
        $geo       = $this->createGeoData();
        $propriete = $this->createPropriete($prop->id, $geo);
        $logement  = $this->createLogementPublie($propriete->id);
        $locataire = $this->createLocataire();

        $demande = Demande::create([
            'logement_id'     => $logement->id,
            'locataire_id'    => $locataire->id,
            'proprietaire_id' => $prop->id,
            'status'          => 'refusee',
            'date_demande'    => now(),
        ]);

        $this->withToken($token)
             ->patchJson("/api/proprietaire/demandes/{$demande->id}/accepter")
             ->assertStatus(400);
    }

    /** @test */
    public function un_proprietaire_ne_peut_pas_agir_sur_la_demande_dun_autre_proprietaire(): void
    {
        ['token' => $token] = $this->createProprietaireAbonne();
        $geo = $this->createGeoData();

        $autreUser = User::factory()->proprietaire()->create();
        $autreProp = Proprietaire::create([
            'user_id'             => $autreUser->id,
            'proprietaire_id'     => 'PROP-77777',
            'subscription_status' => 'active',
        ]);
        $propriete = $this->createPropriete($autreProp->id, $geo);
        $logement  = $this->createLogementPublie($propriete->id);
        $locataire = $this->createLocataire();
        $demande   = $this->createDemande($logement->id, $locataire->id, $autreProp->id);

        $this->withToken($token)
             ->patchJson("/api/proprietaire/demandes/{$demande->id}/accepter")
             ->assertStatus(403);
    }

    // ══════════════════════════════════════════════
    // GESTION DES BAUX
    // ══════════════════════════════════════════════

    /** @test */
    public function un_proprietaire_peut_lister_ses_baux(): void
    {
        ['token' => $token] = $this->createProprietaireAbonne();

        $this->withToken($token)
             ->getJson('/api/proprietaire/baux')
             ->assertStatus(200);
    }

    /** @test */
    public function un_proprietaire_peut_creer_un_bail_a_partir_dune_demande_acceptee(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietaireAbonne();
        $geo       = $this->createGeoData();
        $propriete = $this->createPropriete($prop->id, $geo);
        $logement  = $this->createLogementPublie($propriete->id);
        $locataire = $this->createLocataire();

        $demande = Demande::create([
            'logement_id'     => $logement->id,
            'locataire_id'    => $locataire->id,
            'proprietaire_id' => $prop->id,
            'status'          => 'acceptee',
            'date_demande'    => now(),
        ]);

        $response = $this->withToken($token)->postJson('/api/proprietaire/baux', [
            'demande_id'                 => $demande->id,
            'nombre_mois_caution'        => 2,
            'date_debut'                 => now()->toDateString(),
            'date_fin'                   => now()->addYear()->toDateString(),
            'jour_echeance'              => 5,
            'renouvellement_automatique' => false,
            'conditions_speciales'       => 'Pas d\'animaux',
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('demandes', ['id' => $demande->id, 'status' => 'bail_cree']);
    }

    /** @test */
    public function un_proprietaire_ne_peut_pas_creer_un_bail_sur_une_demande_en_attente(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietaireAbonne();
        $geo       = $this->createGeoData();
        $propriete = $this->createPropriete($prop->id, $geo);
        $logement  = $this->createLogementPublie($propriete->id);
        $locataire = $this->createLocataire();
        $demande   = $this->createDemande($logement->id, $locataire->id, $prop->id);

        $response = $this->withToken($token)->postJson('/api/proprietaire/baux', [
            'demande_id'                 => $demande->id,
            'nombre_mois_caution'        => 2,
            'date_debut'                 => now()->toDateString(),
            'date_fin'                   => now()->addYear()->toDateString(),
            'jour_echeance'              => 5,
            'renouvellement_automatique' => false,
        ]);

        // La demande doit être acceptée avant de créer un bail
        $response->assertStatus(422);
    }

    /** @test */
    public function un_proprietaire_peut_voir_les_paiements_de_loyer(): void
    {
        ['token' => $token] = $this->createProprietaireAbonne();

        $this->withToken($token)
             ->getJson('/api/proprietaire/paiements')
             ->assertStatus(200);
    }

    /** @test */
    public function un_proprietaire_peut_initier_un_abonnement(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietaireSansAbonnement();

        $plan = \App\Models\Plan::create([
            'name'                => 'Pro',
            'slug'                => 'pro',
            'tier'                => 'pro',
            'prix'                => 10000,
            'billing_cycle'       => 'monthly'
        ]);

        $this->mock(\App\Services\PaymentService::class, function ($mock) {
            $mock->shouldReceive('creerSubscription')->andReturn(new \App\Models\Subscription(['id' => 999]));
            $mock->shouldReceive('validerInitiationAbonnement')->andReturn(null);
            $mock->shouldReceive('initierAbonnement')->andReturn([
                (object)[
                    'id'            => 100,
                    'type'          => 'abonnement',
                    'reference'     => 'REF-ABO-100',
                    'paydunyaToken' => 'token-test',
                    'montant'       => 10000,
                    'mode_paiement' => 'wave',
                    'statut'        => 'pending'
                ],
                ['payment_url' => 'http://paydunya.test']
            ]);
        });

        $response = $this->withToken($token)->postJson('/api/abonnements/initier', [
            'plan_id'   => $plan->id,
            'operateur' => 'wave',
            'telephone' => '771234567',
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['success' => true]);
    }
}

