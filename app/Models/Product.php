<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'description',
        'price',
        'quantity',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    public function primaryImage(): HasMany
    {
        return $this->hasMany(Image::class)->where('is_primary', true);
    }
}
