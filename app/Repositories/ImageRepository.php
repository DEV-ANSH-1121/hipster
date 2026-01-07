<?php

namespace App\Repositories;

use App\Models\Image;
use App\Models\Product;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;

class ImageRepository
{
    public function create(array $data): Image
    {
        return Image::create($data);
    }

    public function findByUploadAndProduct(Upload $upload, Product $product): ?Image
    {
        return Image::where('upload_id', $upload->id)
            ->where('product_id', $product->id)
            ->where('variant', 'original')
            ->first();
    }

    public function setPrimaryImage(Product $product, Image $image): void
    {
        DB::transaction(function () use ($product, $image) {
            Image::where('product_id', $product->id)
                ->where('id', '!=', $image->id)
                ->update(['is_primary' => false]);

            $image->update(['is_primary' => true]);
        });
    }

    public function getPrimaryImage(Product $product): ?Image
    {
        return Image::where('product_id', $product->id)
            ->where('is_primary', true)
            ->first();
    }

    public function createVariant(array $data): Image
    {
        return Image::create($data);
    }
}

