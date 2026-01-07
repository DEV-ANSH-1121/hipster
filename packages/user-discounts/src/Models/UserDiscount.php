<?php

namespace Hipster\UserDiscounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDiscount extends Model
{
    protected $fillable = [
        'user_id',
        'discount_id',
        'usage_count',
        'usage_limit',
        'is_active',
        'assigned_at',
        'revoked_at',
    ];

    protected $casts = [
        'usage_count' => 'integer',
        'usage_limit' => 'integer',
        'is_active' => 'boolean',
        'assigned_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', \App\Models\User::class), 'user_id');
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function canUse(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->revoked_at) {
            return false;
        }

        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return $this->discount->isActive();
    }
}

