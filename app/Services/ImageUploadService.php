<?php

namespace App\Services;

use App\Jobs\GenerateImageVariants;
use App\Models\Image;
use App\Models\Product;
use App\Models\Upload;
use App\Repositories\ImageRepository;
use App\Repositories\UploadRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImageUploadService
{
    private const VARIANTS = [256, 512, 1024];

    public function __construct(
        private UploadRepository $uploadRepository,
        private ImageRepository $imageRepository
    ) {}

    public function findUploadByUuid(string $uuid): ?Upload
    {
        return $this->uploadRepository->findByUuid($uuid);
    }

    public function initiateUpload(string $filename, string $mimeType, int $totalSize, int $chunkSize): Upload
    {
        return $this->uploadRepository->create([
            'filename' => $filename,
            'mime_type' => $mimeType,
            'total_size' => $totalSize,
            'chunk_size' => $chunkSize,
            'total_chunks' => (int) ceil($totalSize / $chunkSize),
            'status' => 'uploading',
            'metadata' => [],
        ]);
    }

    public function uploadChunk(string $uuid, int $chunkIndex, string $chunkData): Upload
    {
        $upload = $this->uploadRepository->findByUuid($uuid);
        
        if (!$upload) {
            throw new \RuntimeException('Upload not found');
        }

        if ($upload->status === 'completed') {
            return $upload; // Already completed, no-op
        }

        DB::transaction(function () use ($upload, $chunkIndex, $chunkData) {
            $tempPath = "uploads/temp/{$upload->uuid}/chunk_{$chunkIndex}";
            
            // Store chunk
            Storage::put($tempPath, base64_decode($chunkData));
            
            // Update metadata to track chunks
            $metadata = $upload->metadata ?? [];
            $metadata[$chunkIndex] = true;
            
            $this->uploadRepository->update($upload, [
                'uploaded_chunks' => count($metadata),
                'metadata' => $metadata,
            ]);
        });

        return $upload->fresh();
    }

    public function completeUpload(string $uuid, string $expectedChecksum): Upload
    {
        $upload = $this->uploadRepository->findByUuid($uuid);
        
        if (!$upload) {
            throw new \RuntimeException('Upload not found');
        }

        if ($upload->status === 'completed') {
            return $upload; // Already completed, no-op
        }

        return DB::transaction(function () use ($upload, $expectedChecksum) {
            // Reassemble file from chunks
            $tempPath = "uploads/temp/{$upload->uuid}";
            $finalPath = "uploads/{$upload->uuid}/{$upload->filename}";
            
            $fileContent = '';
            for ($i = 0; $i < $upload->total_chunks; $i++) {
                $chunkPath = "{$tempPath}/chunk_{$i}";
                if (Storage::exists($chunkPath)) {
                    $fileContent .= Storage::get($chunkPath);
                    Storage::delete($chunkPath);
                }
            }

            // Verify checksum
            $actualChecksum = hash('sha256', $fileContent);
            if ($actualChecksum !== $expectedChecksum) {
                $this->uploadRepository->update($upload, ['status' => 'failed']);
                throw new \RuntimeException('Checksum mismatch');
            }

            // Store final file
            Storage::put($finalPath, $fileContent);
            
            // Clean up temp directory
            Storage::deleteDirectory($tempPath);

            // Update upload status
            $this->uploadRepository->update($upload, [
                'status' => 'completed',
                'checksum' => $actualChecksum,
            ]);

            return $upload->fresh();
        });
    }

    public function attachToProduct(Upload $upload, Product $product, bool $setAsPrimary = true): Image
    {
        // Check if already attached (no-op)
        $existingImage = $this->imageRepository->findByUploadAndProduct($upload, $product);
        if ($existingImage) {
            return $existingImage;
        }

        if (!$upload->isComplete()) {
            throw new \RuntimeException('Upload is not complete');
        }

        return DB::transaction(function () use ($upload, $product, $setAsPrimary) {
            // Create original image record
            $image = $this->imageRepository->create([
                'upload_id' => $upload->id,
                'product_id' => $product->id,
                'path' => "uploads/{$upload->uuid}/{$upload->filename}",
                'variant' => 'original',
                'size' => $upload->total_size,
                'is_primary' => $setAsPrimary,
            ]);

            if ($setAsPrimary) {
                $this->imageRepository->setPrimaryImage($product, $image);
            }

            // Queue variant generation
            GenerateImageVariants::dispatch($image);

            return $image;
        });
    }

    public function generateVariants(Image $image): void
    {
        if ($image->variant !== 'original') {
            return;
        }

        $originalPath = $image->path;
        
        if (!Storage::exists($originalPath)) {
            throw new \RuntimeException('Original image not found');
        }

        $imageInfo = getimagesize(Storage::path($originalPath));
        if (!$imageInfo) {
            throw new \RuntimeException('Invalid image file');
        }

        [$originalWidth, $originalHeight] = $imageInfo;
        $aspectRatio = $originalWidth / $originalHeight;

        foreach (self::VARIANTS as $size) {
            $variantPath = $this->generateVariantPath($originalPath, $size);
            
            // Calculate dimensions preserving aspect ratio
            if ($originalWidth > $originalHeight) {
                $width = $size;
                $height = (int) ($size / $aspectRatio);
            } else {
                $height = $size;
                $width = (int) ($size * $aspectRatio);
            }

            $this->resizeImage($originalPath, $variantPath, $width, $height);

            $this->imageRepository->createVariant([
                'upload_id' => $image->upload_id,
                'product_id' => $image->product_id,
                'path' => $variantPath,
                'variant' => (string) $size,
                'width' => $width,
                'height' => $height,
                'size' => Storage::size($variantPath),
                'is_primary' => false,
            ]);
        }
    }

    private function generateVariantPath(string $originalPath, int $size): string
    {
        $pathInfo = pathinfo($originalPath);
        return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . "_{$size}." . $pathInfo['extension'];
    }

    private function resizeImage(string $sourcePath, string $targetPath, int $width, int $height): void
    {
        $sourceFullPath = Storage::path($sourcePath);
        $targetFullPath = Storage::path($targetPath);

        $imageInfo = getimagesize($sourceFullPath);
        $mimeType = $imageInfo['mime'] ?? 'image/jpeg';

        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($sourceFullPath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($sourceFullPath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($sourceFullPath);
                break;
            case 'image/webp':
                $sourceImage = imagecreatefromwebp($sourceFullPath);
                break;
            default:
                throw new \RuntimeException("Unsupported image type: {$mimeType}");
        }

        $targetImage = imagecreatetruecolor($width, $height);
        
        // Preserve transparency for PNG and GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
            imagefilledrectangle($targetImage, 0, 0, $width, $height, $transparent);
        }

        imagecopyresampled(
            $targetImage,
            $sourceImage,
            0, 0, 0, 0,
            $width, $height,
            $imageInfo[0], $imageInfo[1]
        );

        // Ensure directory exists
        $targetDir = dirname($targetFullPath);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($targetImage, $targetFullPath, 90);
                break;
            case 'image/png':
                imagepng($targetImage, $targetFullPath, 9);
                break;
            case 'image/gif':
                imagegif($targetImage, $targetFullPath);
                break;
            case 'image/webp':
                imagewebp($targetImage, $targetFullPath, 90);
                break;
        }

        imagedestroy($sourceImage);
        imagedestroy($targetImage);
    }
}

