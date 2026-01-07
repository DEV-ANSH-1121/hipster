<?php

namespace Hipster\UserDiscounts\Events;

use Hipster\UserDiscounts\Models\Discount;
use Hipster\UserDiscounts\Models\DiscountAudit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscountApplied
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public DiscountAudit $audit,
        public Discount $discount
    ) {}
}

