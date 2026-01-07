<?php

namespace App\Actions;

use App\DTOs\CsvRowData;
use App\Repositories\ProductRepository;
use Illuminate\Support\Facades\DB;

class ProcessCsvRow
{
    public function __construct(
        private ProductRepository $productRepository
    ) {}

    public function execute(CsvRowData $rowData, bool $isDuplicate = false): array
    {
        if ($isDuplicate) {
            return ['status' => 'duplicate', 'product' => null];
        }

        try {
            $product = $this->productRepository->upsertBySku([
                'sku' => $rowData->sku,
                'name' => $rowData->name,
                'description' => $rowData->description,
                'price' => $rowData->price,
                'quantity' => $rowData->quantity,
                'is_active' => $rowData->isActive,
            ]);

            $wasRecentlyCreated = $product->wasRecentlyCreated;

            return [
                'status' => $wasRecentlyCreated ? 'created' : 'updated',
                'product' => $product,
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'product' => null, 'error' => $e->getMessage()];
        }
    }
}

