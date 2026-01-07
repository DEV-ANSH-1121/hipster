<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Image extends Model
{
    protected $fillable = [
        'upload_id',
        'product_id',
        'path',
        'variant',
        'width',
        'height',
        'size',
        'is_primary',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'size' => 'integer',
        'is_primary' => 'boolean',
    ];

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
