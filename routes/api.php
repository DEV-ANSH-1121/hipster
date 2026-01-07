<?php

use App\Http\Controllers\ImageUploadController;
use App\Http\Controllers\ProductImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    // CSV Import
    Route::post('/products/import', [ProductImportController::class, 'import']);

    // Image Upload
    Route::post('/uploads/initiate', [ImageUploadController::class, 'initiate']);
    Route::post('/uploads/chunk', [ImageUploadController::class, 'uploadChunk']);
    Route::post('/uploads/complete', [ImageUploadController::class, 'complete']);
    Route::post('/uploads/attach', [ImageUploadController::class, 'attach']);
});

