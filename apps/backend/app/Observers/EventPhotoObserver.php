<?php

namespace App\Observers;

use App\Models\EventPhoto;
use App\Services\PhotoIndexingDispatcher;
use Illuminate\Support\Facades\Log;

class EventPhotoObserver
{
    public function created(EventPhoto $photo): void
    {
        if (! $photo->original_filename) {
            $photo->update([
                'original_filename' => basename($photo->storage_path),
            ]);
        }

        if (! config('services.ml_process.auto_index_on_upload', false)) {
            return;
        }

        if ($photo->status !== 'uploaded') {
            return;
        }

        try {
            app(PhotoIndexingDispatcher::class)->queue($photo);
        } catch (\Throwable $exception) {
            Log::warning('Auto indexing dispatch failed for uploaded photo.', [
                'photo_id' => $photo->id,
                'event_id' => $photo->event_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
