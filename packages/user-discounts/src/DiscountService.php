<?php

namespace Hipster\UserDiscounts;

use Hipster\UserDiscounts\Events\DiscountApplied;
use Hipster\UserDiscounts\Events\DiscountAssigned;
use Hipster\UserDiscounts\Events\DiscountRevoked;
use Hipster\UserDiscounts\Models\Discount;
use Hipster\UserDiscounts\Models\DiscountAudit;
use Hipster\UserDiscounts\Models\UserDiscount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class DiscountService
{
    public function assign($user, Discount $discount): UserDiscount
    {
        return DB::transaction(function () use ($user, $discount) {
            $userDiscount = UserDiscount::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'discount_id' => $discount->id,
                ],
                [
                    'is_active' => true,
                    'assigned_at' => now(),
                ]
            );

            if ($userDiscount->wasRecentlyCreated) {
                Event::dispatch(new DiscountAssigned($userDiscount, $discount));
            }

            return $userDiscount;
        });
    }

    public function revoke($user, Discount $discount): bool
    {
        return DB::transaction(function () use ($user, $discount) {
            $userDiscount = UserDiscount::where('user_id', $user->id)
                ->where('discount_id', $discount->id)
                ->whereNull('revoked_at')
                ->first();

            if (!$userDiscount) {
                return false;
            }

            $userDiscount->update([
                'is_active' => false,
                'revoked_at' => now(),
            ]);

            Event::dispatch(new DiscountRevoked($userDiscount, $discount));

            return true;
        });
    }

    public function eligibleFor($user): Collection
    {
        $userDiscounts = UserDiscount::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->with('discount')
            ->get();

        return $userDiscounts->filter(function ($userDiscount) {
            return $userDiscount->canUse() && $userDiscount->discount->isActive();
        });
    }

    public function apply($user, float $amount): array
    {
        $eligibleDiscounts = $this->eligibleFor($user);
        
        if ($eligibleDiscounts->isEmpty()) {
            return [
                'original_amount' => $amount,
                'discount_amount' => 0.0,
                'final_amount' => $amount,
                'applied_discounts' => [],
            ];
        }

        return DB::transaction(function () use ($user, $amount, $eligibleDiscounts) {
            $stackingOrder = config('user-discounts.stacking_order', 'desc');
            $maxCap = config('user-discounts.max_discount_cap', 50.0);
            $rounding = config('user-discounts.rounding', 2);

            // Sort discounts by percentage
            $sortedDiscounts = $eligibleDiscounts->sortBy(function ($userDiscount) {
                return $userDiscount->discount->percentage;
            }, SORT_REGULAR, $stackingOrder === 'desc');

            $totalDiscountPercent = 0.0;
            $appliedDiscounts = [];
            $discountAmount = 0.0;

            foreach ($sortedDiscounts as $userDiscount) {
                $discount = $userDiscount->discount;
                $discountPercent = $discount->percentage;
                $newTotalPercent = $totalDiscountPercent + $discountPercent;

                // Check max cap
                if ($newTotalPercent > $maxCap) {
                    $discountPercent = $maxCap - $totalDiscountPercent;
                    if ($discountPercent <= 0) {
                        break;
                    }
                }

                $discountValue = round(($amount * $discountPercent) / 100, $rounding);
                $discountAmount += $discountValue;
                $totalDiscountPercent += $discountPercent;

                // Increment usage count atomically
                UserDiscount::where('id', $userDiscount->id)
                    ->where('usage_count', $userDiscount->usage_count)
                    ->increment('usage_count');

                // Create audit record
                $audit = DiscountAudit::create([
                    'user_id' => $user->id,
                    'discount_id' => $discount->id,
                    'action' => 'applied',
                    'original_amount' => $amount,
                    'discount_amount' => $discountValue,
                    'final_amount' => $amount - $discountAmount,
                    'metadata' => [
                        'stacking_order' => $stackingOrder,
                        'total_percentage' => $totalDiscountPercent,
                    ],
                ]);

                Event::dispatch(new DiscountApplied($audit, $discount));

                $appliedDiscounts[] = [
                    'discount_id' => $discount->id,
                    'code' => $discount->code,
                    'percentage' => $discountPercent,
                    'amount' => $discountValue,
                ];

                if ($totalDiscountPercent >= $maxCap) {
                    break;
                }
            }

            $finalAmount = round($amount - $discountAmount, $rounding);

            return [
                'original_amount' => $amount,
                'discount_amount' => round($discountAmount, $rounding),
                'final_amount' => $finalAmount,
                'applied_discounts' => $appliedDiscounts,
            ];
        });
    }
}

