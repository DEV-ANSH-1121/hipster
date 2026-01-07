<?php

namespace App\DTOs;

class CsvImportResult
{
    public function __construct(
        public int $total = 0,
        public int $created = 0,
        public int $updated = 0,
        public int $invalid = 0,
        public int $duplicates = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'created' => $this->created,
            'updated' => $this->updated,
            'invalid' => $this->invalid,
            'duplicates' => $this->duplicates,
        ];
    }
}

