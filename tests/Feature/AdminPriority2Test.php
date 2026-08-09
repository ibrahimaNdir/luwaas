<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Plan;
use App\Models\Proprietaire;
use App\Models\Subscription;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPriority2Test extends TestCase
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
    public function change_plan_vers_free_remet_le_bailleur_en_free_trial_15_jours(): void
    {
        ['token' => $token] = $this->createAdmin();
        $proprietaire = $this->createProprietairePro();
        $planFree = Plan::where('slug', 'free')->first();

        $this->withToken($token)
            ->patchJson("/api/admin/subscriptions/{$proprietaire->id}/change-plan", [
                'plan_id' => $planFree->id,
            ])
            ->assertStatus(200);

        $proprietaire->refresh();

        $this->assertSame('free_trial', $proprietaire->subscription_status);
        $this->assertSame('free', $proprietaire->plan);
        $this->assertNull($proprietaire->billing_cycle);
        $this->assertTrue($proprietaire->trial_ends_at->greaterThan(now()->addDays(14)));
        $this->assertTrue($proprietaire->canPublishLogement());
    }

    /** @test */
    public function le_mrr_admin_compte_les_abonnements_actifs_normalises(): void
    {
        ['token' => $token] = $this->createAdmin();
        $proprietaire = $this->createProprietaireEssai();
        $planMonthly = Plan::where('slug', 'pro-monthly')->first();
        $planYearly  = Plan::where('slug', 'pro-yearly')->first();

        Subscription::create([
            'proprietaire_id' => $proprietaire->id,
            'plan_id'         => $planMonthly->id,
            'status'          => 'active',
            'amount'          => 5000,
            'starts_at'       => now()->subMonth(),
            'ends_at'         => now()->addMonth(),
        ]);

        $pro2 = Proprietaire::create([
            'user_id'              => User::factory()->proprietaire()->create()->id,
            'proprietaire_id'      => 'PROP-MRR-2',
            'subscription_status'  => 'active',
            'plan'                 => 'pro',
            'billing_cycle'        => 'yearly',
            'subscription_ends_at' => now()->addYear(),
            'is_actif'             => true,
        ]);

        Subscription::create([
            'proprietaire_id' => $pro2->id,
            'plan_id'         => $planYearly->id,
            'status'          => 'active',
            'amount'          => 48000,
            'starts_at'       => now()->subMonths(2),
            'ends_at'         => now()->addMonths(10),
        ]);

        $response = $this->withToken($token)->getJson('/api/admin/stats');

        $response->assertStatus(200);
        // 5000 monthly + 48000/12 yearly = 5000 + 4000 = 9000
        $this->assertSame(9000.0, (float) $response->json('data.mrr'));
    }

    /** @test */
    public function annuler_un_abonnement_conserve_l_acces_pro_jusqu_a_la_fin(): void
    {
        ['token' => $token] = $this->createAdmin();
        $proprietaire = $this->createProprietairePro();
        $plan = Plan::where('slug', 'pro-monthly')->first();
        $endsAt = now()->addDays(20);

        Subscription::create([
            'proprietaire_id' => $proprietaire->id,
            'plan_id'         => $plan->id,
            'status'          => 'active',
            'amount'          => 5000,
            'starts_at'       => now()->subDays(10),
            'ends_at'         => $endsAt,
        ]);

        $proprietaire->update([
            'subscription_status'  => 'active',
            'subscription_ends_at' => $endsAt,
        ]);

        $this->withToken($token)
            ->patchJson("/api/admin/subscriptions/{$proprietaire->id}/cancel")
            ->assertStatus(200);

        $proprietaire->refresh();

        $this->assertSame('cancelled', $proprietaire->subscription_status);
        $this->assertTrue($proprietaire->hasActiveSubscription());
        $this->assertTrue($proprietaire->canPublishLogement());
    }

    private function createAdmin(): array
    {
        $user = User::factory()->admin()->create(['phone_verified_at' => now()]);

        Admin::create([
            'user_id'   => $user->id,
            'admin_id'  => 'ADM-P2-' . $user->id,
            'username'  => 'admin_p2',
            'is_active' => true,
        ]);

        return ['token' => $user->createToken('admin-token')->plainTextToken];
    }

    private function createProprietaireEssai(): Proprietaire
    {
        $user = User::factory()->proprietaire()->create(['phone_verified_at' => now()]);

        return Proprietaire::create([
            'user_id'             => $user->id,
            'proprietaire_id'     => 'PROP-P2-FREE',
            'subscription_status' => 'free_trial',
            'plan'                => 'free',
            'trial_ends_at'       => now()->addDays(15),
            'is_actif'            => true,
        ]);
    }

    private function createProprietairePro(): Proprietaire
    {
        $user = User::factory()->proprietaire()->create(['phone_verified_at' => now()]);

        return Proprietaire::create([
            'user_id'              => $user->id,
            'proprietaire_id'      => 'PROP-P2-PRO',
            'subscription_status'  => 'active',
            'plan'                 => 'pro',
            'billing_cycle'        => 'monthly',
            'subscription_ends_at' => now()->addMonth(),
            'is_actif'             => true,
        ]);
    }
}
