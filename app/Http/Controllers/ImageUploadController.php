<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachImageRequest;
use App\Http\Requests\CompleteUploadRequest;
use App\Http\Requests\InitiateUploadRequest;
use App\Http\Requests\UploadChunkRequest;
use App\Models\Product;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;

class ImageUploadController extends Controller
{
    public function __construct(
        private ImageUploadService $uploadService
    ) {}

    public function initiate(InitiateUploadRequest $request): JsonResponse
    {
        $upload = $this->uploadService->initiateUpload(
            filename: $request->input('filename'),
            mimeType: $request->input('mime_type'),
            totalSize: $request->input('total_size'),
            chunkSize: $request->input('chunk_size')
        );

        return response()->json([
            'success' => true,
            'data' => [
                'uuid' => $upload->uuid,
                'total_chunks' => $upload->total_chunks,
            ],
        ]);
    }

    public function uploadChunk(UploadChunkRequest $request): JsonResponse
    {
        $upload = $this->uploadService->uploadChunk(
            uuid: $request->input('uuid'),
            chunkIndex: $request->input('chunk_index'),
            chunkData: $request->input('chunk_data')
        );

        return response()->json([
            'success' => true,
            'data' => [
                'uploaded_chunks' => $upload->uploaded_chunks,
                'total_chunks' => $upload->total_chunks,
                'status' => $upload->status,
            ],
        ]);
    }

    public function complete(CompleteUploadRequest $request): JsonResponse
    {
        $upload = $this->uploadService->completeUpload(
            uuid: $request->input('uuid'),
            expectedChecksum: $request->input('checksum')
        );

        return response()->json([
            'success' => true,
            'data' => [
                'uuid' => $upload->uuid,
                'status' => $upload->status,
                'checksum' => $upload->checksum,
            ],
        ]);
    }

    public function attach(AttachImageRequest $request): JsonResponse
    {
        $product = Product::findOrFail($request->input('product_id'));
        
        $upload = $this->uploadService->findUploadByUuid(
            $request->input('upload_uuid')
        );

        if (!$upload) {
            return response()->json([
                'success' => false,
                'message' => 'Upload not found',
            ], 404);
        }

        $image = $this->uploadService->attachToProduct(
            upload: $upload,
            product: $product,
            setAsPrimary: $request->input('set_as_primary', true)
        );

        return response()->json([
            'success' => true,
            'data' => [
                'image_id' => $image->id,
                'product_id' => $image->product_id,
                'is_primary' => $image->is_primary,
            ],
        ]);
    }
}
