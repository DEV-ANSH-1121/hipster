<?php

namespace App\Jobs;

use App\Models\Image;
use App\Services\ImageUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateImageVariants implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Image $image
    ) {}

    public function handle(ImageUploadService $imageUploadService): void
    {
        $imageUploadService->generateVariants($this->image);
    }
}

