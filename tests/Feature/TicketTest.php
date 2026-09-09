<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Locataire;
use App\Models\Proprietaire;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    private function createLocataire(): array
    {
        $user = User::factory()->locataire()->create(['phone_verified_at' => now()]);
        Locataire::create([
            'user_id'      => $user->id,
            'locataire_id' => 'LOC-' . $user->id,
            'is_actif'     => true,
        ]);
        return ['user' => $user, 'token' => $user->createToken('t')->plainTextToken];
    }

    private function createProprietaire(): array
    {
        $user = User::factory()->proprietaire()->create(['phone_verified_at' => now()]);
        Proprietaire::create([
            'user_id'             => $user->id,
            'proprietaire_id'     => 'PROP-' . $user->id,
            'subscription_status' => 'active',
            'is_actif'            => true,
        ]);
        return ['user' => $user, 'token' => $user->createToken('t')->plainTextToken];
    }

    private function createAdmin(): array
    {
        $user = User::factory()->admin()->create(['phone_verified_at' => now()]);
        Admin::create([
            'user_id'   => $user->id,
            'admin_id'  => 'ADM-' . $user->id,
            'username'  => 'admin_tickets',
            'is_active' => true,
        ]);
        return ['user' => $user, 'token' => $user->createToken('t')->plainTextToken];
    }

    // ─────────────────────────────────────────────
    // Tests Locataire
    // ─────────────────────────────────────────────

    /** @test */
    public function un_locataire_peut_creer_un_ticket(): void
    {
        ['token' => $token, 'user' => $user] = $this->createLocataire();

        $response = $this->withToken($token)->postJson('/api/tickets', [
            'sujet'   => 'Problème de paiement',
            'message' => 'Je n\'arrive pas à effectuer mon paiement de loyer.',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.sujet', 'Problème de paiement')
                 ->assertJsonPath('data.statut', 'ouvert')
                 ->assertJsonPath('data.user_id', $user->id);

        $this->assertDatabaseHas('tickets', [
            'user_id' => $user->id,
            'statut'  => 'ouvert',
        ]);
    }

    /** @test */
    public function un_locataire_voit_uniquement_ses_tickets(): void
    {
        ['token' => $token, 'user' => $user] = $this->createLocataire();
        ['user' => $autreUser] = $this->createLocataire();

        Ticket::create(['user_id' => $user->id, 'sujet' => 'Mon ticket', 'message' => 'Message']);
        Ticket::create(['user_id' => $autreUser->id, 'sujet' => 'Autre ticket', 'message' => 'Message']);

        $response = $this->withToken($token)->getJson('/api/tickets');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Mon ticket', $response->json('data.data.0.sujet'));
    }

    /** @test */
    public function un_locataire_peut_voir_son_ticket(): void
    {
        ['token' => $token, 'user' => $user] = $this->createLocataire();

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'sujet'   => 'Mon ticket',
            'message' => 'Message test',
        ]);

        $response = $this->withToken($token)->getJson("/api/tickets/{$ticket->id}");

        $response->assertOk()->assertJsonPath('data.id', $ticket->id);
    }

    /** @test */
    public function un_locataire_ne_peut_pas_voir_le_ticket_de_quelquun_dautre(): void
    {
        ['token' => $token] = $this->createLocataire();
        ['user' => $autreUser] = $this->createLocataire();

        $ticket = Ticket::create([
            'user_id' => $autreUser->id,
            'sujet'   => 'Ticket privé',
            'message' => 'Message',
        ]);

        $this->withToken($token)->getJson("/api/tickets/{$ticket->id}")->assertStatus(404);
    }

    /** @test */
    public function un_proprietaire_peut_aussi_creer_un_ticket(): void
    {
        ['token' => $token, 'user' => $user] = $this->createProprietaire();

        $response = $this->withToken($token)->postJson('/api/tickets', [
            'sujet'   => 'Bug sur le tableau de bord',
            'message' => 'Je ne vois plus mes logements.',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.user_id', $user->id);
    }

    /** @test */
    public function la_creation_dun_ticket_echoue_sans_sujet(): void
    {
        ['token' => $token] = $this->createLocataire();

        $this->withToken($token)->postJson('/api/tickets', [
            'message' => 'Message sans sujet',
        ])->assertStatus(422)->assertJsonValidationErrors(['sujet']);
    }

    // ─────────────────────────────────────────────
    // Tests Admin
    // ─────────────────────────────────────────────

    /** @test */
    public function ladmin_voit_tous_les_tickets(): void
    {
        ['token' => $adminToken] = $this->createAdmin();
        ['user' => $u1] = $this->createLocataire();
        ['user' => $u2] = $this->createLocataire();

        Ticket::create(['user_id' => $u1->id, 'sujet' => 'Ticket 1', 'message' => 'Msg']);
        Ticket::create(['user_id' => $u2->id, 'sujet' => 'Ticket 2', 'message' => 'Msg']);

        $response = $this->withToken($adminToken)->getJson('/api/admin/tickets');

        $response->assertOk();
        $this->assertCount(2, $response->json('data.data'));
    }

    /** @test */
    public function ladmin_peut_repondre_a_un_ticket(): void
    {
        ['token' => $adminToken] = $this->createAdmin();
        ['user' => $user] = $this->createLocataire();

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'sujet'   => 'Question',
            'message' => 'Comment ça marche ?',
            'statut'  => 'ouvert',
        ]);

        $response = $this->withToken($adminToken)->postJson("/api/admin/tickets/{$ticket->id}/repondre", [
            'reponse_admin' => 'Voici la réponse à votre question.',
        ]);

        $response->assertOk()->assertJsonPath('data.statut', 'en_cours');

        $this->assertDatabaseHas('tickets', [
            'id'            => $ticket->id,
            'statut'        => 'en_cours',
            'reponse_admin' => 'Voici la réponse à votre question.',
        ]);
    }

    /** @test */
    public function ladmin_peut_fermer_un_ticket(): void
    {
        ['token' => $adminToken] = $this->createAdmin();
        ['user' => $user] = $this->createLocataire();

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'sujet'   => 'Ticket à fermer',
            'message' => 'Résolu',
            'statut'  => 'en_cours',
        ]);

        $response = $this->withToken($adminToken)->patchJson("/api/admin/tickets/{$ticket->id}/fermer");

        $response->assertOk()->assertJsonPath('data.statut', 'ferme');
        $this->assertNotNull($response->json('data.closed_at'));
    }

    /** @test */
    public function ladmin_ne_peut_pas_fermer_un_ticket_deja_ferme(): void
    {
        ['token' => $adminToken] = $this->createAdmin();
        ['user' => $user] = $this->createLocataire();

        $ticket = Ticket::create([
            'user_id'   => $user->id,
            'sujet'     => 'Déjà fermé',
            'message'   => 'Message',
            'statut'    => 'ferme',
            'closed_at' => now(),
        ]);

        $this->withToken($adminToken)->patchJson("/api/admin/tickets/{$ticket->id}/fermer")
            ->assertStatus(422);
    }

    /** @test */
    public function ladmin_voit_les_stats_tickets(): void
    {
        ['token' => $adminToken] = $this->createAdmin();
        ['user' => $user] = $this->createLocataire();

        Ticket::create(['user_id' => $user->id, 'sujet' => 'T1', 'message' => 'M', 'statut' => 'ouvert']);
        Ticket::create(['user_id' => $user->id, 'sujet' => 'T2', 'message' => 'M', 'statut' => 'en_cours']);
        Ticket::create(['user_id' => $user->id, 'sujet' => 'T3', 'message' => 'M', 'statut' => 'ferme']);

        $response = $this->withToken($adminToken)->getJson('/api/admin/tickets/stats');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(1, $data['ouverts']);
        $this->assertEquals(1, $data['en_cours']);
        $this->assertEquals(1, $data['fermes']);
        $this->assertEquals(3, $data['total']);
    }

    /** @test */
    public function ladmin_peut_filtrer_tickets_par_statut(): void
    {
        ['token' => $adminToken] = $this->createAdmin();
        ['user' => $user] = $this->createLocataire();

        Ticket::create(['user_id' => $user->id, 'sujet' => 'Ouvert', 'message' => 'M', 'statut' => 'ouvert']);
        Ticket::create(['user_id' => $user->id, 'sujet' => 'Fermé', 'message' => 'M', 'statut' => 'ferme']);

        $response = $this->withToken($adminToken)->getJson('/api/admin/tickets?statut=ouvert');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Ouvert', $response->json('data.data.0.sujet'));
    }
}
