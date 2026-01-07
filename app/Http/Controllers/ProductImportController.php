<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportCsvRequest;
use App\Services\ProductImportService;
use Illuminate\Http\JsonResponse;

class ProductImportController extends Controller
{
    public function __construct(
        private ProductImportService $importService
    ) {}

    public function import(ImportCsvRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $filePath = $file->getRealPath();

        $result = $this->importService->import($filePath);

        return response()->json([
            'success' => true,
            'data' => $result->toArray(),
        ]);
    }
}
