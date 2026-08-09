<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Proprietaire;
use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ExpireSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(NotificationService::class, function ($mock) {
            $mock->shouldReceive('sendToUser')->andReturn(true);
            $mock->shouldReceive('sendToMultipleUsers')->andReturn(1);
        });
    }

    /** @test */
    public function la_commande_bascule_un_abonnement_expire_en_grace_period(): void
    {
        Notification::fake();

        $user = User::factory()->proprietaire()->create(['phone_verified_at' => now()]);

        $proprietaire = Proprietaire::create([
            'user_id'              => $user->id,
            'proprietaire_id'      => 'PROP-EXP-1',
            'subscription_status'  => 'active',
            'plan'                 => 'pro',
            'subscription_ends_at' => now()->subDay(),
            'is_actif'             => true,
        ]);

        Artisan::call('subscriptions:expire');

        $this->assertSame('grace_period', $proprietaire->fresh()->subscription_status);
    }

    /** @test */
    public function la_commande_ne_expire_pas_un_abonnement_actif(): void
    {
        Notification::fake();

        $user = User::factory()->proprietaire()->create(['phone_verified_at' => now()]);

        $proprietaire = Proprietaire::create([
            'user_id'              => $user->id,
            'proprietaire_id'      => 'PROP-ACTIVE-1',
            'subscription_status'  => 'active',
            'plan'                 => 'pro',
            'subscription_ends_at' => now()->addDays(10),
            'is_actif'             => true,
        ]);

        Artisan::call('subscriptions:expire');

        $this->assertSame('active', $proprietaire->fresh()->subscription_status);
    }

    /** @test */
    public function le_resume_financier_admin_utilise_les_statuts_transactions_reels(): void
    {
        ['token' => $token] = $this->createAdmin();

        Transaction::create([
            'type'             => 'subscription_payment',
            'mode_paiement'    => 'wave',
            'montant'          => 5000,
            'statut'           => 'valide',
            'date_transaction' => now(),
        ]);

        Transaction::create([
            'type'             => 'subscription_payment',
            'mode_paiement'    => 'wave',
            'montant'          => 3000,
            'statut'           => 'rejete',
            'date_transaction' => now(),
        ]);

        Transaction::create([
            'type'             => 'subscription_payment',
            'mode_paiement'    => 'wave',
            'montant'          => 2000,
            'statut'           => 'en_attente',
            'date_transaction' => now(),
        ]);

        $response = $this->withToken($token)->getJson('/api/admin/transactions/summary');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertSame(5000.0, (float) $data['total_ce_mois']);
        $this->assertSame(1, $data['nombre_ce_mois']);
        $this->assertSame(5000.0, (float) $data['total_global']);
        $this->assertSame(1, $data['total_echouees']);
        $this->assertSame(1, $data['total_en_attente']);
    }

    private function createAdmin(): array
    {
        $user = User::factory()->admin()->create(['phone_verified_at' => now()]);

        Admin::create([
            'user_id'   => $user->id,
            'admin_id'  => 'ADM-' . $user->id,
            'username'  => 'admin_stats',
            'is_active' => true,
        ]);

        return [
            'token' => $user->createToken('admin-token')->plainTextToken,
        ];
    }
}
