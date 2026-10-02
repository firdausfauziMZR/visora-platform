<?php

namespace App\Jobs;

use App\Models\EventPhoto;
use App\Services\ImageVariantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeneratePhotoVariants implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public EventPhoto $photo) {}

    public function handle(ImageVariantService $variantService): void
    {
        $variants = $variantService->generate($this->photo);

        if ($variants === []) {
            return;
        }

        $metadata = $this->photo->metadata ?? [];
        $metadata['variants'] = $variants;

        $this->photo->update(['metadata' => $metadata]);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::warning('Photo variant generation failed.', [
            'photo_id' => $this->photo->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
