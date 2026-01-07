<?php

namespace Hipster\UserDiscounts\Events;

use Hipster\UserDiscounts\Models\Discount;
use Hipster\UserDiscounts\Models\UserDiscount;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscountAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public UserDiscount $userDiscount,
        public Discount $discount
    ) {}
}

