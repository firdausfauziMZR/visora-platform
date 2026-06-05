<?php

namespace App\Jobs;

use App\Models\PhotoIndexingJob;
use App\Services\LocalMlProcess;
use App\Services\PgvectorFaceSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class IndexEventPhoto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public PhotoIndexingJob $indexingJob) {}

    public function handle(LocalMlProcess $mlProcess): void
    {
        $this->indexingJob->loadMissing('photo');

        $photo = $this->indexingJob->photo;
        $disk = Storage::disk($photo->storage_disk);

        if (! $disk->exists($photo->storage_path)) {
            throw new RuntimeException("Photo file does not exist: {$photo->storage_path}");
        }

        $this->indexingJob->update([
            'status' => 'running',
            'attempts' => $this->indexingJob->attempts + 1,
            'started_at' => now(),
            'error_message' => null,
        ]);

        $photo->update([
            'status' => 'indexing',
        ]);

        $payload = $mlProcess->indexPhoto($photo, $disk->path($photo->storage_path));

        $photo->faceEmbeddings()->delete();

        foreach (($payload['faces'] ?? []) as $index => $face) {
            $embedding = $face['embedding'] ?? null;

            if (! is_array($embedding)) {
                continue;
            }

            $photo->faceEmbeddings()->create([
                'event_id' => $photo->event_id,
                'face_index' => $index,
                'box' => $face['box'] ?? null,
                'embedding' => PgvectorFaceSearch::vectorLiteral($embedding),
            ]);
        }

        $this->indexingJob->update([
            'status' => 'completed',
            'ml_status' => $payload['status'] ?? null,
            'response_payload' => $payload,
            'completed_at' => now(),
        ]);

        $photo->update([
            'status' => 'indexed',
            'faces_detected' => $payload['faces_detected'] ?? $photo->faces_detected,
            'indexed_at' => now(),
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        $this->indexingJob->loadMissing('photo');

        $this->indexingJob->update([
            'status' => 'failed',
            'error_message' => $exception?->getMessage(),
        ]);

        $this->indexingJob->photo?->update([
            'status' => 'failed',
        ]);
    }

}
