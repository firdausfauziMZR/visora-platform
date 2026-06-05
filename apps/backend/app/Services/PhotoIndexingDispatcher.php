<?php

namespace App\Services;

use App\Jobs\IndexEventPhoto;
use App\Models\EventPhoto;
use App\Models\PhotoIndexingJob;
use Throwable;

class PhotoIndexingDispatcher
{
    public function queue(EventPhoto $photo): PhotoIndexingJob
    {
        $job = $this->createIndexingJob($photo);

        IndexEventPhoto::dispatch($job);

        return $job;
    }

    public function runNow(EventPhoto $photo): PhotoIndexingJob
    {
        $job = $this->createIndexingJob($photo);
        $indexEventPhoto = new IndexEventPhoto($job);

        try {
            app()->call([$indexEventPhoto, 'handle']);
        } catch (Throwable $exception) {
            $indexEventPhoto->failed($exception);

            throw $exception;
        }

        return $job->refresh();
    }

    private function createIndexingJob(EventPhoto $photo): PhotoIndexingJob
    {
        $job = PhotoIndexingJob::create([
            'event_id' => $photo->event_id,
            'event_photo_id' => $photo->id,
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        $photo->update([
            'status' => 'queued',
        ]);

        return $job;
    }
}
