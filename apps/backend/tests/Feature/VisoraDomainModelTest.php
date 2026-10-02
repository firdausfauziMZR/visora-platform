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

    public function test_event_access_approval_relationships_work(): void
    {
        $user = User::factory()->create();

        $event = Event::create([
            'name' => 'Approved Event',
            'slug' => 'approved-event',
            'is_active' => true,
        ]);

        $this->assertFalse($user->canAccessEvent($event));
        $this->assertFalse($event->isAccessibleBy($user));

        $event->approvedUsers()->attach($user->id, ['approved_at' => now()]);

        $this->assertTrue($user->canAccessEvent($event));
        $this->assertTrue($event->isAccessibleBy($user));
        $this->assertSame($event->id, $user->approvedEvents()->first()->id);
    }

    public function test_admin_can_access_events_without_customer_approval(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $event = Event::create([
            'name' => 'Admin Event',
            'slug' => 'admin-event',
            'is_active' => true,
        ]);

        $this->assertTrue($admin->canAccessEvent($event));
        $this->assertTrue($event->isAccessibleBy($admin));
    }
}
