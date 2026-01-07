<?php

namespace Hipster\UserDiscounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discount extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'percentage',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function userDiscounts(): HasMany
    {
        return $this->hasMany(UserDiscount::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(DiscountAudit::class);
    }

    public function isActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();
        
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }
}

