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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualPaymentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function un_proprietaire_peut_marquer_un_paiement_comme_regle_manuellement(): void
    {
        // ── Géographie
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
            'type_propriete'  => 'immeuble',
            'adresse'         => 'Rue Test',
            'ville'           => 'Dakar',
            'region_id'       => $region->id,
            'departement_id'  => $dept->id,
            'commune_id'      => $commune->id,
        ]);

        $logement = Logement::create([
            'propriete_id'    => $propriete->id,
            'numero'          => 'A1',
            'typelogement'    => 'appartement',
            'nombre_chambres' => 2,
            'nombre_salles_de_bain' => 1,
            'surface'         => 50,
            'prix_loyer'      => 150000,
            'statut_occupe'   => 'libre',
            'statut_publication' => 'publie',
        ]);

        // ── Locataire
        $userLoc   = User::factory()->locataire()->create();
        $locataire = Locataire::create([
            'user_id'      => $userLoc->id,
            'locataire_id' => 'LOC-1',
            'is_actif'     => true,
        ]);

        // ── Bail & Paiement
        $bail = Bail::create([
            'logement_id'         => $logement->id,
            'locataire_id'        => $locataire->id,
            'proprietaire_id'     => $proprietaire->id,
            'date_debut'          => now()->format('Y-m-d'),
            'date_fin'            => now()->addYear()->format('Y-m-d'),
            'montant_loyer'       => 150000,
            'nombre_mois_caution' => 2,
            'montant_caution_total' => 300000,
            'montant_caution_signature' => 150000,
            'statut'              => 'en_attente_paiement',
        ]);

        $paiement = Paiement::create([
            'bail_id'         => $bail->id,
            'locataire_id'    => $locataire->id,
            'type'            => 'loyer_mensuel',
            'montant_attendu' => 150000,
            'montant_paye'    => 0,
            'montant_restant' => 150000,
            'date_echeance'   => now()->format('Y-m-d'),
            'statut'          => 'impayé',
            'mois'            => now()->month,
            'annee'           => now()->year,
        ]);

        // Créer un abonnement actif pour passer le middleware subscribed
        $plan = \App\Models\Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'tier' => 'pro',
            'prix' => 5000,
            'billing_cycle' => 'monthly',
            'biens_max' => 10,
            'locataires_max' => 10,
            'cogestionnaires_max' => 1
        ]);
        
        \App\Models\Subscription::create([
            'proprietaire_id' => $proprietaire->id,
            'plan_id' => $plan->id,
            'statut' => 'active',
            'amount' => 5000,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        // ── Appel API
        $response = $this->actingAs($userProp)
                         ->patchJson("/api/proprietaire/paiements/{$paiement->id}/manuel", [
                             'mode' => 'especes'
                         ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // ── Vérification
        $this->assertDatabaseHas('paiements', [
            'id'              => $paiement->id,
            'statut'          => 'payé',
            'montant_paye'    => 150000,
            'montant_restant' => 0,
        ]);

        $this->assertDatabaseHas('transactions', [
            'paiement_id'   => $paiement->id,
            'type'          => 'rent_payment',
            'mode_paiement' => 'especes',
            'montant'       => 150000,
            'statut'        => 'valide',
        ]);
    }

    /** @test */
    public function un_proprietaire_ne_peut_pas_marquer_le_paiement_dun_autre(): void
    {
        // ── Géographie
        $region    = \App\Models\Region::create(['nom' => 'Dakar', 'code' => 'DK']);
        $dept      = \App\Models\Departement::create(['nom' => 'Dakar', 'code' => 'DK01', 'region_id' => $region->id]);
        $commune   = \App\Models\Commune::create(['nom' => 'Plateau', 'code' => 'PLT', 'departement_id' => $dept->id]);

        $userProp1     = User::factory()->proprietaire()->create();
        $proprietaire1 = Proprietaire::create(['user_id' => $userProp1->id, 'proprietaire_id' => 'P1', 'is_actif' => true]);

        $userProp2     = User::factory()->proprietaire()->create();
        $proprietaire2 = Proprietaire::create(['user_id' => $userProp2->id, 'proprietaire_id' => 'P2', 'is_actif' => true]);

        $propriete = Propriete::create([
            'proprietaire_id' => $proprietaire1->id,
            'titre' => 'Test', 'type_propriete' => 'immeuble', 'adresse' => 'Rue 1',
            'ville' => 'Dakar', 'region_id' => $region->id, 'departement_id' => $dept->id, 'commune_id' => $commune->id,
        ]);
        
        $logement = Logement::create(['propriete_id' => $propriete->id, 'numero' => 'A1', 'typelogement' => 'appartement', 'nombre_chambres' => 2, 'nombre_salles_de_bain' => 1, 'surface' => 50, 'prix_loyer' => 150000, 'statut_occupe' => 'libre', 'statut_publication' => 'publie']);
        
        $locataire = Locataire::create(['user_id' => User::factory()->locataire()->create()->id, 'locataire_id' => 'L1', 'is_actif' => true]);
        
        $bail = Bail::create([
            'logement_id' => $logement->id, 'locataire_id' => $locataire->id, 'proprietaire_id' => $proprietaire1->id,
            'date_debut' => now(), 'date_fin' => now()->addYear(), 'montant_loyer' => 150000, 'nombre_mois_caution' => 2, 'montant_caution_total' => 300000, 'montant_caution_signature' => 150000, 'statut' => 'en_attente_paiement',
        ]);

        $paiement = Paiement::create([
            'bail_id' => $bail->id, 'locataire_id' => $locataire->id, 'type' => 'loyer_mensuel',
            'montant_attendu' => 150000, 'montant_paye' => 0, 'montant_restant' => 150000,
            'date_echeance' => now(), 'statut' => 'impayé', 'mois' => 1, 'annee' => 2024,
        ]);

        // Créer un abonnement actif pour passer le middleware subscribed
        $plan = \App\Models\Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'tier' => 'pro',
            'prix' => 5000,
            'billing_cycle' => 'monthly',
            'biens_max' => 10,
            'locataires_max' => 10,
            'cogestionnaires_max' => 1
        ]);
        
        \App\Models\Subscription::create([
            'proprietaire_id' => $proprietaire2->id,
            'plan_id' => $plan->id,
            'statut' => 'active',
            'amount' => 5000,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        // P2 essaie de valider le paiement de P1
        $response = $this->actingAs($userProp2)
                         ->patchJson("/api/proprietaire/paiements/{$paiement->id}/manuel");

        $response->assertStatus(403);
    }
}
