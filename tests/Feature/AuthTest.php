<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Locataire;
use App\Models\Proprietaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Tests d'authentification — inscription, OTP, connexion, déconnexion
 * pour les trois rôles : propriétaire, locataire, admin
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ══════════════════════════════════════════════
    // INSCRIPTION
    // ══════════════════════════════════════════════

    /** @test */
    public function un_proprietaire_peut_sinscrire(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/register', [
            'prenom'    => 'Ibrahima',
            'nom'       => 'Diallo',
            'email'     => 'ibrahima@test.sn',
            'telephone' => '771234567',
            'password'  => 'secret123',
            'user_type' => 'proprietaire',
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['message' => 'Inscription réussie. Un code OTP a été envoyé sur votre email.'])
                 ->assertJsonStructure(['user_id']);

        $this->assertDatabaseHas('users', [
            'email'     => 'ibrahima@test.sn',
            'user_type' => 'proprietaire',
        ]);

        $user = User::where('email', 'ibrahima@test.sn')->first();
        $this->assertDatabaseHas('proprietaires', ['user_id' => $user->id]);
        $this->assertNotNull($user->phone_otp);
    }

    /** @test */
    public function un_locataire_peut_sinscrire(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/register', [
            'prenom'    => 'Fatou',
            'nom'       => 'Sow',
            'email'     => 'fatou@test.sn',
            'telephone' => '771111111',
            'password'  => 'secret123',
            'user_type' => 'locataire',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'fatou@test.sn')->first();
        $this->assertDatabaseHas('locataires', ['user_id' => $user->id]);
    }

    /** @test */
    public function linscription_echoue_avec_email_deja_utilise(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'existant@test.sn']);

        $response = $this->postJson('/api/auth/register', [
            'prenom'    => 'Test',
            'nom'       => 'User',
            'email'     => 'existant@test.sn',
            'telephone' => '779999999',
            'password'  => 'secret123',
            'user_type' => 'locataire',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function linscription_echoue_avec_user_type_invalide(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'prenom'    => 'Test',
            'nom'       => 'User',
            'email'     => 'test@test.sn',
            'telephone' => '778888888',
            'password'  => 'secret123',
            'user_type' => 'admin', // admin non autorisé à l'inscription publique
        ]);

        $response->assertStatus(422);
    }

    // ══════════════════════════════════════════════
    // VÉRIFICATION OTP
    // ══════════════════════════════════════════════

    /** @test */
    public function un_utilisateur_peut_verifier_son_otp(): void
    {
        $otp = '123456';

        $user = User::factory()->create([
            'phone_verified_at'    => null,
            'phone_otp'            => Hash::make($otp),
            'phone_otp_expires_at' => now()->addMinutes(10),
            'otp_attempts'         => 0,
        ]);

        $response = $this->postJson('/api/auth/verify-otp', [
            'user_id' => $user->id,
            'otp'     => $otp,
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['token', 'user'])
                 ->assertJsonFragment(['message' => 'Compte vérifié ! Bienvenue sur Luwaas.']);

        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    /** @test */
    public function la_verification_otp_echoue_avec_un_code_incorrect(): void
    {
        $user = User::factory()->create([
            'phone_verified_at'    => null,
            'phone_otp'            => Hash::make('123456'),
            'phone_otp_expires_at' => now()->addMinutes(10),
            'otp_attempts'         => 0,
        ]);

        $response = $this->postJson('/api/auth/verify-otp', [
            'user_id' => $user->id,
            'otp'     => '000000',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function la_verification_otp_echoue_avec_code_expire(): void
    {
        $otp = '123456';

        $user = User::factory()->create([
            'phone_verified_at'    => null,
            'phone_otp'            => Hash::make($otp),
            'phone_otp_expires_at' => now()->subMinutes(5), // expiré
            'otp_attempts'         => 0,
        ]);

        $response = $this->postJson('/api/auth/verify-otp', [
            'user_id' => $user->id,
            'otp'     => $otp,
        ]);

        $response->assertStatus(422)
                 ->assertJsonFragment(['message' => 'Code expiré. Demandez un nouveau code.']);
    }

    /** @test */
    public function la_verification_est_bloquee_apres_3_tentatives(): void
    {
        $user = User::factory()->create([
            'phone_verified_at'    => null,
            'phone_otp'            => Hash::make('123456'),
            'phone_otp_expires_at' => now()->addMinutes(10),
            'otp_attempts'         => 3,
        ]);

        $response = $this->postJson('/api/auth/verify-otp', [
            'user_id' => $user->id,
            'otp'     => '123456',
        ]);

        $response->assertStatus(429);
    }

    // ══════════════════════════════════════════════
    // CONNEXION
    // ══════════════════════════════════════════════

    /** @test */
    public function un_utilisateur_verifie_peut_se_connecter(): void
    {
        $user = User::factory()->create([
            'email'             => 'login@test.sn',
            'password'          => Hash::make('secret123'),
            'phone_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login'    => 'login@test.sn',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['token', 'user', 'redirect']);
    }

    /** @test */
    public function la_connexion_echoue_avec_mauvais_mot_de_passe(): void
    {
        User::factory()->create([
            'email'    => 'user@test.sn',
            'password' => Hash::make('correct'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login'    => 'user@test.sn',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401);
    }

    // ══════════════════════════════════════════════
    // DÉCONNEXION
    // ══════════════════════════════════════════════

    /** @test */
    public function un_utilisateur_connecte_peut_se_deconnecter(): void
    {
        $user = User::factory()->create(['phone_verified_at' => now()]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
                         ->postJson('/api/logout');

        $response->assertStatus(200)
                 ->assertJsonFragment(['message' => 'Déconnexion réussie']);
    }

    /** @test */
    public function un_utilisateur_non_authentifie_ne_peut_pas_se_deconnecter(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }

    // ══════════════════════════════════════════════
    // RENVOI OTP
    // ══════════════════════════════════════════════

    /** @test */
    public function un_utilisateur_peut_renvoyer_son_otp(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'phone_verified_at'    => null,
            'phone_otp'            => Hash::make('111111'),
            'phone_otp_expires_at' => now()->subMinutes(15),
        ]);

        $response = $this->postJson('/api/auth/resend-otp', [
            'user_id' => $user->id,
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['message' => 'Nouveau code envoyé sur votre email !']);
    }
}
