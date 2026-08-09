<?php

namespace Tests\Feature;

use App\Models\Locataire;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\ScoreLocataireService;

class ScoreLocataireTest extends TestCase
{
    use RefreshDatabase;

    private function createLocataire()
    {
        $user = User::factory()->locataire()->create();
        return Locataire::create([
            'user_id' => $user->id,
            'locataire_id' => 'LOC-' . $user->id,
            'is_actif' => true,
            'score_fiabilite' => 100,
        ]);
    }

    private function createPaiement(Locataire $locataire, $dateEcheance)
    {
        return Paiement::create([
            'locataire_id' => $locataire->id,
            // Pour ce test unitaire du service, on n'a pas besoin de bail
            'bail_id' => 1, 
            'type' => 'loyer_mensuel',
            'montant_attendu' => 1000,
            'statut' => 'impayé',
            'date_echeance' => $dateEcheance
        ]);
    }

    public function test_paiement_en_ligne_a_temps_augmente_score()
    {
        $locataire = $this->createLocataire();
        $paiement = $this->createPaiement($locataire, now()->addDays(5)->format('Y-m-d'));

        $service = new ScoreLocataireService();
        $service->mettreAJourScore($locataire, $paiement);

        $locataire->refresh();
        
        $this->assertEquals(102, $locataire->score_fiabilite); // 100 + 2
        $this->assertEquals(1, $locataire->total_paiements_en_ligne);
        $this->assertEquals(1, $locataire->total_paiements_a_temps);
        $this->assertEquals(0, $locataire->total_paiements_en_retard);
    }

    public function test_paiement_en_ligne_en_retard_diminue_score()
    {
        $locataire = $this->createLocataire();
        $paiement = $this->createPaiement($locataire, now()->subDays(5)->format('Y-m-d'));

        $service = new ScoreLocataireService();
        $service->mettreAJourScore($locataire, $paiement);

        $locataire->refresh();
        
        $this->assertEquals(90, $locataire->score_fiabilite); // 100 - 10
        $this->assertEquals(1, $locataire->total_paiements_en_ligne);
        $this->assertEquals(0, $locataire->total_paiements_a_temps);
        $this->assertEquals(1, $locataire->total_paiements_en_retard);
    }

    public function test_locataire_peut_voir_son_propre_score()
    {
        $locataire = $this->createLocataire();
        $locataire->update(['score_fiabilite' => 100]);

        $response = $this->actingAs($locataire->user)->getJson('/api/locataire/mon-score');

        $response->assertStatus(200)
                 ->assertJsonPath('score', 100);
    }
}
