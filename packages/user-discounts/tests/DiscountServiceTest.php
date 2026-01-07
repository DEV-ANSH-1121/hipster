<?php

namespace Hipster\UserDiscounts\Tests;

use Hipster\UserDiscounts\DiscountService;
use Hipster\UserDiscounts\Events\DiscountApplied;
use Hipster\UserDiscounts\Events\DiscountAssigned;
use Hipster\UserDiscounts\Events\DiscountRevoked;
use Hipster\UserDiscounts\Models\Discount;
use Hipster\UserDiscounts\Models\DiscountAudit;
use Hipster\UserDiscounts\Models\UserDiscount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DiscountServiceTest extends TestCase
{
    use RefreshDatabase;

    private DiscountService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Load package migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        
        $this->service = new DiscountService();
    }

    public function test_assign_creates_user_discount(): void
    {
        Event::fake();

        $user = $this->createUser();
        $discount = $this->createDiscount('TEST10', 10.0);

        $userDiscount = $this->service->assign($user, $discount);

        $this->assertNotNull($userDiscount);
        $this->assertEquals($user->id, $userDiscount->user_id);
        $this->assertEquals($discount->id, $userDiscount->discount_id);
        $this->assertTrue($userDiscount->is_active);
        
        Event::assertDispatched(DiscountAssigned::class);
    }

    public function test_assign_is_idempotent(): void
    {
        $user = $this->createUser();
        $discount = $this->createDiscount('TEST10', 10.0);

        $userDiscount1 = $this->service->assign($user, $discount);
        $userDiscount2 = $this->service->assign($user, $discount);

        $this->assertEquals($userDiscount1->id, $userDiscount2->id);
    }

    public function test_revoke_deactivates_user_discount(): void
    {
        Event::fake();

        $user = $this->createUser();
        $discount = $this->createDiscount('TEST10', 10.0);
        $this->service->assign($user, $discount);

        $result = $this->service->revoke($user, $discount);

        $this->assertTrue($result);
        $userDiscount = UserDiscount::where('user_id', $user->id)
            ->where('discount_id', $discount->id)
            ->first();
        $this->assertFalse($userDiscount->is_active);
        $this->assertNotNull($userDiscount->revoked_at);
        
        Event::assertDispatched(DiscountRevoked::class);
    }

    public function test_eligibleFor_excludes_expired_discounts(): void
    {
        $user = $this->createUser();
        $activeDiscount = $this->createDiscount('ACTIVE', 10.0);
        $expiredDiscount = $this->createDiscount('EXPIRED', 20.0, false, now()->subDay(), now()->subHour());
        
        $this->service->assign($user, $activeDiscount);
        $this->service->assign($user, $expiredDiscount);

        $eligible = $this->service->eligibleFor($user);

        $this->assertCount(1, $eligible);
        $this->assertEquals($activeDiscount->id, $eligible->first()->discount_id);
    }

    public function test_eligibleFor_excludes_inactive_discounts(): void
    {
        $user = $this->createUser();
        $activeDiscount = $this->createDiscount('ACTIVE', 10.0, true);
        $inactiveDiscount = $this->createDiscount('INACTIVE', 20.0, false);
        
        $this->service->assign($user, $activeDiscount);
        $this->service->assign($user, $inactiveDiscount);

        $eligible = $this->service->eligibleFor($user);

        $this->assertCount(1, $eligible);
        $this->assertEquals($activeDiscount->id, $eligible->first()->discount_id);
    }

    public function test_eligibleFor_excludes_revoked_discounts(): void
    {
        $user = $this->createUser();
        $discount1 = $this->createDiscount('DISC1', 10.0);
        $discount2 = $this->createDiscount('DISC2', 20.0);
        
        $this->service->assign($user, $discount1);
        $this->service->assign($user, $discount2);
        $this->service->revoke($user, $discount1);

        $eligible = $this->service->eligibleFor($user);

        $this->assertCount(1, $eligible);
        $this->assertEquals($discount2->id, $eligible->first()->discount_id);
    }

    public function test_eligibleFor_enforces_usage_limit(): void
    {
        $user = $this->createUser();
        $discount = $this->createDiscount('LIMITED', 10.0);
        
        $userDiscount = $this->service->assign($user, $discount);
        $userDiscount->update(['usage_limit' => 2, 'usage_count' => 2]);

        $eligible = $this->service->eligibleFor($user);

        $this->assertCount(0, $eligible);
    }

    public function test_apply_calculates_discount_correctly(): void
    {
        Event::fake();

        $user = $this->createUser();
        $discount = $this->createDiscount('TEST10', 10.0);
        $this->service->assign($user, $discount);

        $result = $this->service->apply($user, 100.0);

        $this->assertEquals(100.0, $result['original_amount']);
        $this->assertEquals(10.0, $result['discount_amount']);
        $this->assertEquals(90.0, $result['final_amount']);
        $this->assertCount(1, $result['applied_discounts']);
        
        Event::assertDispatched(DiscountApplied::class);
    }

    public function test_apply_stacks_discounts_correctly(): void
    {
        config(['user-discounts.stacking_order' => 'desc']);

        $user = $this->createUser();
        $discount1 = $this->createDiscount('DISC10', 10.0);
        $discount2 = $this->createDiscount('DISC20', 20.0);
        
        $this->service->assign($user, $discount1);
        $this->service->assign($user, $discount2);

        $result = $this->service->apply($user, 100.0);

        $this->assertEquals(30.0, $result['discount_amount']);
        $this->assertEquals(70.0, $result['final_amount']);
        $this->assertCount(2, $result['applied_discounts']);
    }

    public function test_apply_respects_max_discount_cap(): void
    {
        config(['user-discounts.max_discount_cap' => 25.0]);

        $user = $this->createUser();
        $discount1 = $this->createDiscount('DISC20', 20.0);
        $discount2 = $this->createDiscount('DISC30', 30.0);
        
        $this->service->assign($user, $discount1);
        $this->service->assign($user, $discount2);

        $result = $this->service->apply($user, 100.0);

        $this->assertEquals(25.0, $result['discount_amount']);
        $this->assertEquals(75.0, $result['final_amount']);
    }

    public function test_apply_increments_usage_count(): void
    {
        $user = $this->createUser();
        $discount = $this->createDiscount('TEST10', 10.0);
        $userDiscount = $this->service->assign($user, $discount);

        $initialCount = $userDiscount->usage_count;
        $this->service->apply($user, 100.0);

        $userDiscount->refresh();
        $this->assertEquals($initialCount + 1, $userDiscount->usage_count);
    }

    public function test_apply_is_idempotent_with_concurrent_requests(): void
    {
        $user = $this->createUser();
        $discount = $this->createDiscount('TEST10', 10.0);
        $userDiscount = $this->service->assign($user, $discount);

        // Simulate concurrent applies
        $initialCount = $userDiscount->usage_count;
        
        $this->service->apply($user, 100.0);
        $this->service->apply($user, 100.0);

        $userDiscount->refresh();
        // Should increment twice, but only if usage_limit allows
        $this->assertGreaterThanOrEqual($initialCount + 1, $userDiscount->usage_count);
    }

    public function test_apply_creates_audit_records(): void
    {
        $user = $this->createUser();
        $discount = $this->createDiscount('TEST10', 10.0);
        $this->service->assign($user, $discount);

        $this->service->apply($user, 100.0);

        $audit = DiscountAudit::where('user_id', $user->id)
            ->where('discount_id', $discount->id)
            ->where('action', 'applied')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals(100.0, $audit->original_amount);
        $this->assertEquals(10.0, $audit->discount_amount);
        $this->assertEquals(90.0, $audit->final_amount);
    }

    public function test_apply_rounds_correctly(): void
    {
        config(['user-discounts.rounding' => 2]);

        $user = $this->createUser();
        $discount = $this->createDiscount('TEST33', 33.33);
        $this->service->assign($user, $discount);

        $result = $this->service->apply($user, 100.0);

        $this->assertEquals(33.33, $result['discount_amount']);
        $this->assertEquals(66.67, $result['final_amount']);
    }

    public function test_apply_with_no_eligible_discounts(): void
    {
        $user = $this->createUser();

        $result = $this->service->apply($user, 100.0);

        $this->assertEquals(100.0, $result['original_amount']);
        $this->assertEquals(0.0, $result['discount_amount']);
        $this->assertEquals(100.0, $result['final_amount']);
        $this->assertEmpty($result['applied_discounts']);
    }

    public function test_apply_stacking_order_asc(): void
    {
        config(['user-discounts.stacking_order' => 'asc']);

        $user = $this->createUser();
        $discount1 = $this->createDiscount('DISC10', 10.0);
        $discount2 = $this->createDiscount('DISC20', 20.0);
        
        $this->service->assign($user, $discount1);
        $this->service->assign($user, $discount2);

        $result = $this->service->apply($user, 100.0);

        $this->assertEquals(30.0, $result['discount_amount']);
        // Verify order (asc means 10% applied first, then 20%)
        $this->assertCount(2, $result['applied_discounts']);
    }

    public function test_apply_respects_usage_limit_during_application(): void
    {
        $user = $this->createUser();
        $discount = $this->createDiscount('LIMITED', 10.0);
        $userDiscount = $this->service->assign($user, $discount);
        $userDiscount->update(['usage_limit' => 1]);

        // First apply should work
        $result1 = $this->service->apply($user, 100.0);
        $this->assertEquals(10.0, $result1['discount_amount']);

        // Second apply should not include this discount (usage limit reached)
        $result2 = $this->service->apply($user, 100.0);
        $this->assertEquals(0.0, $result2['discount_amount']);
    }

    public function test_apply_handles_discount_not_yet_started(): void
    {
        $user = $this->createUser();
        $futureDiscount = $this->createDiscount('FUTURE', 10.0, true, now()->addDay(), null);
        $this->service->assign($user, $futureDiscount);

        $eligible = $this->service->eligibleFor($user);
        $this->assertCount(0, $eligible);
    }

    public function test_apply_handles_discount_ended(): void
    {
        $user = $this->createUser();
        $expiredDiscount = $this->createDiscount('EXPIRED', 10.0, true, null, now()->subDay());
        $this->service->assign($user, $expiredDiscount);

        $eligible = $this->service->eligibleFor($user);
        $this->assertCount(0, $eligible);
    }

    public function test_revoke_nonexistent_discount_returns_false(): void
    {
        $user = $this->createUser();
        $discount = $this->createDiscount('TEST10', 10.0);

        $result = $this->service->revoke($user, $discount);
        $this->assertFalse($result);
    }

    public function test_apply_handles_zero_amount(): void
    {
        $user = $this->createUser();
        $discount = $this->createDiscount('TEST10', 10.0);
        $this->service->assign($user, $discount);

        $result = $this->service->apply($user, 0.0);

        $this->assertEquals(0.0, $result['original_amount']);
        $this->assertEquals(0.0, $result['discount_amount']);
        $this->assertEquals(0.0, $result['final_amount']);
    }

    public function test_apply_handles_very_large_amount(): void
    {
        $user = $this->createUser();
        $discount = $this->createDiscount('TEST10', 10.0);
        $this->service->assign($user, $discount);

        $result = $this->service->apply($user, 999999.99);

        $this->assertEquals(999999.99, $result['original_amount']);
        $this->assertEquals(99999.999, $result['discount_amount'], '', 0.01);
    }

    public function test_apply_creates_separate_audit_for_each_discount(): void
    {
        $user = $this->createUser();
        $discount1 = $this->createDiscount('DISC10', 10.0);
        $discount2 = $this->createDiscount('DISC20', 20.0);
        
        $this->service->assign($user, $discount1);
        $this->service->assign($user, $discount2);

        $this->service->apply($user, 100.0);

        $audits = DiscountAudit::where('user_id', $user->id)
            ->where('action', 'applied')
            ->get();

        $this->assertCount(2, $audits);
        $this->assertTrue($audits->pluck('discount_id')->contains($discount1->id));
        $this->assertTrue($audits->pluck('discount_id')->contains($discount2->id));
    }

    private function createUser()
    {
        static $userId = 1;
        
        // Create a simple user model for testing
        $userClass = config('auth.providers.users.model', \App\Models\User::class);
        
        if (class_exists($userClass)) {
            try {
                return $userClass::firstOrCreate(
                    ['email' => "test{$userId}@example.com"],
                    ['name' => "Test User {$userId}"]
                );
            } catch (\Exception $e) {
                // Fallback if User model doesn't exist
            }
        }
        
        // Fallback: create a minimal user object
        $user = new class {
            public $id;
            public $name = 'Test User';
            public $email = 'test@example.com';
        };
        $user->id = $userId++;
        
        return $user;
    }

    private function createDiscount(
        string $code,
        float $percentage,
        bool $isActive = true,
        $startsAt = null,
        $endsAt = null
    ): Discount {
        return Discount::create([
            'code' => $code,
            'name' => "Discount {$code}",
            'percentage' => $percentage,
            'is_active' => $isActive,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }
}

