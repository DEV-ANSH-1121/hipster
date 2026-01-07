<?php

namespace App\Repositories;

use App\Models\Upload;

class UploadRepository
{
    public function findByUuid(string $uuid): ?Upload
    {
        return Upload::where('uuid', $uuid)->first();
    }

    public function create(array $data): Upload
    {
        return Upload::create($data);
    }

    public function update(Upload $upload, array $data): Upload
    {
        $upload->update($data);
        return $upload->fresh();
    }

    public function incrementChunks(Upload $upload, int $count = 1): void
    {
        $upload->increment('uploaded_chunks', $count);
    }
}

