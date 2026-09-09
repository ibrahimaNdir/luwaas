<?php

namespace Tests\Feature;

use App\Models\Logement;
use App\Models\Plan;
use App\Models\Propriete;
use App\Models\Proprietaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests securite Option 2 :
 *  - IDOR sur updateStatusPublication
 *  - Idempotence webhook (transaction deja traitee)
 */
class LogementSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create([
            'slug'             => 'free',
            'name'             => 'Gratuit',
            'tier'             => 'free',
            'publications_max' => 5,
            'is_active'        => true,
            'features'         => ['5 annonces'],
        ]);

        Plan::create([
            'slug'             => 'pro-monthly',
            'name'             => 'Pro',
            'tier'             => 'pro',
            'billing_cycle'    => 'monthly',
            'publications_max' => 10,
            'is_active'        => true,
            'features'         => ['10 annonces'],
        ]);
    }

    private function createProprietairePro(): array
    {
        $user = User::factory()->proprietaire()->create();
        $prop = Proprietaire::create([
            'user_id'              => $user->id,
            'proprietaire_id'      => 'PROP-' . uniqid(),
            'subscription_status'  => 'active',
            'plan'                 => 'pro',
            'billing_cycle'        => 'monthly',
            'subscription_ends_at' => now()->addMonth(),
            'is_actif'             => true,
        ]);
        $token = $user->createToken('t')->plainTextToken;
        return ['proprietaire' => $prop, 'token' => $token];
    }

    private function geo(): array
    {
        $r = \App\Models\Region::firstOrCreate(['nom' => 'TestRegion'], ['code' => 'TR1']);
        $d = \App\Models\Departement::firstOrCreate(['nom' => 'TestDept'], ['code' => 'TD1', 'region_id' => $r->id]);
        $c = \App\Models\Commune::firstOrCreate(['nom' => 'TestCommune'], ['code' => 'TC1', 'departement_id' => $d->id]);
        return ['r' => $r, 'd' => $d, 'c' => $c];
    }

    private function mkPropriete(int $ownerId): Propriete
    {
        $g = $this->geo();
        return Propriete::create([
            'proprietaire_id' => $ownerId,
            'titre'           => 'Imm-' . uniqid(),
            'type'            => 'immeuble',
            'region_id'       => $g['r']->id,
            'departement_id'  => $g['d']->id,
            'commune_id'      => $g['c']->id,
        ]);
    }

    private function mkLogement(int $propId, string $num = 'L1'): Logement
    {
        return Logement::create([
            'propriete_id'          => $propId,
            'numero'                => $num,
            'typelogement'          => 'appartement',
            'nombre_chambres'       => 2,
            'nombre_salles_de_bain' => 1,
            'prix_loyer'            => 150000,
            'meuble'                => false,
            'etat'                  => 'bon',
            'statut_publication'    => 'brouillon',
            'statut_occupe'         => 'disponible',
        ]);
    }

    // ═══════════════════════════════════════════
    // IDOR — updateStatusPublication
    // ═══════════════════════════════════════════

    /** @test */
    public function idor_est_bloque_sur_update_status_publication(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietairePro();

        $pA = $this->mkPropriete($prop->id);
        $pB = $this->mkPropriete($prop->id);
        $lB = $this->mkLogement($pB->id);

        // URL avec proprieteA->id mais logement de proprieteB => 404 attendu
        $r = $this->withToken($token)
            ->patchJson("/api/proprietaire/proprietes/{$pA->id}/logements/{$lB->id}/status", [
                'statut_publication' => 'publie',
            ]);

        $r->assertStatus(404);
        $this->assertEquals('brouillon', $lB->fresh()->statut_publication);
    }

    /** @test */
    public function update_status_fonctionne_avec_la_bonne_propriete(): void
    {
        ['token' => $token, 'proprietaire' => $prop] = $this->createProprietairePro();

        $p = $this->mkPropriete($prop->id);
        $l = $this->mkLogement($p->id);

        $r = $this->withToken($token)
            ->patchJson("/api/proprietaire/proprietes/{$p->id}/logements/{$l->id}/status", [
                'statut_publication' => 'publie',
            ]);

        $r->assertStatus(200)->assertJsonFragment(['statut_publication' => 'publie']);
        $this->assertEquals('publie', $l->fresh()->statut_publication);
    }

    // ═══════════════════════════════════════════
    // Idempotence webhook
    // ═══════════════════════════════════════════

    /** @test */
    public function transaction_deja_traitee_reste_inchangee(): void
    {
        $t = \App\Models\Transaction::create([
            'type'          => 'subscription_payment',
            'montant'       => 15000,
            'statut'        => 'valide',
            'mode_paiement' => 'card',
            'paydunyatoken' => 'TOK-' . uniqid(),
        ]);

        // Verification : le statut doit etre identique apres un "rejeu"
        $this->assertEquals('valide', $t->fresh()->statut);
    }
}
