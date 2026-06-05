<?php

namespace Tests\Feature;

use App\Models\DownloadUnlock;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\PhotoIndexingJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisoraDomainModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_photo_indexing_job_and_download_unlock_relationships_work(): void
    {
        $user = User::factory()->create();

        $event = Event::create([
            'name' => 'Local Marathon',
            'slug' => 'local-marathon',
            'venue' => 'City Park',
            'is_active' => true,
        ]);

        $photo = EventPhoto::create([
            'event_id' => $event->id,
            'original_filename' => 'runner.jpg',
            'storage_path' => 'event-photos/runner.jpg',
            'status' => 'uploaded',
        ]);

        $job = PhotoIndexingJob::create([
            'event_id' => $event->id,
            'event_photo_id' => $photo->id,
            'status' => 'queued',
        ]);

        $unlock = DownloadUnlock::create([
            'event_photo_id' => $photo->id,
            'user_id' => $user->id,
            'customer_email' => 'customer@example.test',
            'status' => 'unlocked',
        ]);

        $this->assertTrue($event->photos()->whereKey($photo)->exists());
        $this->assertTrue($photo->indexingJobs()->whereKey($job)->exists());
        $this->assertSame($event->id, $job->event->id);
        $this->assertSame($photo->id, $unlock->photo->id);
        $this->assertSame($user->id, $unlock->user->id);
    }
}
