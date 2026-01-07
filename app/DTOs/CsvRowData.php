<?php

namespace App\DTOs;

class CsvRowData
{
    public function __construct(
        public string $sku,
        public string $name,
        public ?string $description = null,
        public ?float $price = null,
        public int $quantity = 0,
        public bool $isActive = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            sku: $data['sku'] ?? '',
            name: $data['name'] ?? '',
            description: $data['description'] ?? null,
            price: isset($data['price']) ? (float) $data['price'] : null,
            quantity: isset($data['quantity']) ? (int) $data['quantity'] : 0,
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : true,
        );
    }
}

