<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductRepository
{
    public function findBySku(string $sku): ?Product
    {
        return Product::where('sku', $sku)->first();
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);
        return $product->fresh();
    }

    public function upsertBySku(array $data): Product
    {
        return Product::updateOrCreate(
            ['sku' => $data['sku']],
            $data
        );
    }

    public function existsBySku(string $sku): bool
    {
        return Product::where('sku', $sku)->exists();
    }
}

