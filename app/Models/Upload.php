<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Upload extends Model
{
    protected $fillable = [
        'uuid',
        'filename',
        'mime_type',
        'total_size',
        'chunk_size',
        'total_chunks',
        'uploaded_chunks',
        'checksum',
        'status',
        'metadata',
    ];

    protected $casts = [
        'total_size' => 'integer',
        'chunk_size' => 'integer',
        'total_chunks' => 'integer',
        'uploaded_chunks' => 'integer',
        'metadata' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($upload) {
            if (empty($upload->uuid)) {
                $upload->uuid = (string) Str::uuid();
            }
        });
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    public function isComplete(): bool
    {
        return $this->status === 'completed' && $this->uploaded_chunks >= $this->total_chunks;
    }
}
