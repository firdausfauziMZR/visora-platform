<?php

namespace Tests\Feature;

use App\Jobs\IndexEventPhoto;
use App\Jobs\GeneratePhotoVariants;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Services\LocalMlProcess;
use App\Services\PhotoIndexingDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoIndexingDispatcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.ml_process.auto_index_on_upload' => false]);
        config(['services.image_variants.enabled' => false]);
    }

    public function test_queue_creates_indexing_job_updates_photo_status_and_dispatches_job(): void
    {
        Bus::fake();

        $event = Event::create([
            'name' => 'Local Marathon',
            'slug' => 'local-marathon',
        ]);

        $photo = EventPhoto::create([
            'event_id' => $event->id,
            'storage_path' => 'event-photos/runner.jpg',
            'status' => 'uploaded',
        ]);

        $job = app(PhotoIndexingDispatcher::class)->queue($photo);

        $this->assertDatabaseHas('photo_indexing_jobs', [
            'id' => $job->id,
            'event_id' => $event->id,
            'event_photo_id' => $photo->id,
            'status' => 'queued',
        ]);

        $this->assertSame('queued', $photo->refresh()->status);
        Bus::assertDispatched(IndexEventPhoto::class);
    }

    public function test_index_event_photo_runs_local_ml_process_and_marks_photo_indexed(): void
    {
        config(['services.image_variants.enabled' => true]);
        Bus::fake([GeneratePhotoVariants::class]);
        Storage::fake('local');
        $this->mock(LocalMlProcess::class)
            ->shouldReceive('indexPhoto')
            ->once()
            ->andReturn([
                'status' => 'indexed',
                'event_id' => '1',
                'photo_id' => '1',
                'filename' => 'runner.jpg',
                'faces_detected' => 2,
                'embedding_status' => 'generated',
                'body_embedding_status' => 'generated',
                'body_embeddings_count' => 1,
                'faces' => [
                    [
                        'box' => ['x' => 1, 'y' => 2, 'width' => 30, 'height' => 40],
                        'embedding' => array_fill(0, 128, 0.1),
                    ],
                    [
                        'box' => ['x' => 10, 'y' => 20, 'width' => 50, 'height' => 60],
                        'embedding' => array_fill(0, 128, 0.2),
                    ],
                ],
                'body_embeddings' => [
                    [
                        'body_index' => 0,
                        'source_face_index' => 0,
                        'box' => ['x' => 0, 'y' => 0, 'width' => 120, 'height' => 240],
                        'embedding' => array_fill(0, 512, 0.01),
                    ],
                ],
            ]);

        Storage::disk('local')->put('event-photos/runner.jpg', 'fake-image-bytes');

        $event = Event::create([
            'name' => 'Local Marathon',
            'slug' => 'local-marathon',
        ]);

        $photo = EventPhoto::create([
            'event_id' => $event->id,
            'original_filename' => 'runner.jpg',
            'storage_disk' => 'local',
            'storage_path' => 'event-photos/runner.jpg',
            'status' => 'queued',
        ]);

        $indexingJob = $photo->indexingJobs()->create([
            'event_id' => $event->id,
            'status' => 'queued',
        ]);

        app()->call([new IndexEventPhoto($indexingJob), 'handle']);

        $this->assertSame('completed', $indexingJob->refresh()->status);
        $this->assertSame('indexed', $indexingJob->ml_status);
        $this->assertSame('indexed', $photo->refresh()->status);
        $this->assertSame(2, $photo->faces_detected);
        $this->assertSame(2, $photo->faceEmbeddings()->count());
        $this->assertSame(1, $photo->bodyEmbeddings()->count());
        Bus::assertDispatched(GeneratePhotoVariants::class);
    }

    public function test_run_now_indexes_without_dispatching_queue_job(): void
    {
        Bus::fake();
        Storage::fake('local');
        $this->mock(LocalMlProcess::class)
            ->shouldReceive('indexPhoto')
            ->once()
            ->andReturn([
                'status' => 'indexed',
                'event_id' => '1',
                'photo_id' => '1',
                'filename' => 'runner.jpg',
                'faces_detected' => 3,
                'embedding_status' => 'generated',
                'body_embedding_status' => 'generated',
                'body_embeddings_count' => 2,
                'faces' => [
                    [
                        'box' => ['x' => 1, 'y' => 2, 'width' => 30, 'height' => 40],
                        'embedding' => array_fill(0, 128, 0.1),
                    ],
                    [
                        'box' => ['x' => 10, 'y' => 20, 'width' => 50, 'height' => 60],
                        'embedding' => array_fill(0, 128, 0.2),
                    ],
                    [
                        'box' => ['x' => 100, 'y' => 200, 'width' => 70, 'height' => 80],
                        'embedding' => array_fill(0, 128, 0.3),
                    ],
                ],
                'body_embeddings' => [
                    [
                        'body_index' => 0,
                        'source_face_index' => 0,
                        'box' => ['x' => 0, 'y' => 0, 'width' => 120, 'height' => 240],
                        'embedding' => array_fill(0, 512, 0.01),
                    ],
                    [
                        'body_index' => 1,
                        'source_face_index' => 1,
                        'box' => ['x' => 10, 'y' => 20, 'width' => 120, 'height' => 240],
                        'embedding' => array_fill(0, 512, 0.02),
                    ],
                ],
            ]);

        Storage::disk('local')->put('event-photos/runner.jpg', 'fake-image-bytes');

        $event = Event::create([
            'name' => 'Local Marathon',
            'slug' => 'local-marathon-sync',
        ]);

        $photo = EventPhoto::create([
            'event_id' => $event->id,
            'original_filename' => 'runner.jpg',
            'storage_disk' => 'local',
            'storage_path' => 'event-photos/runner.jpg',
            'status' => 'uploaded',
        ]);

        $job = app(PhotoIndexingDispatcher::class)->runNow($photo);

        Bus::assertNotDispatched(IndexEventPhoto::class);
        $this->assertSame('completed', $job->status);
        $this->assertSame('indexed', $photo->refresh()->status);
        $this->assertSame(3, $photo->faces_detected);
        $this->assertSame(3, $photo->faceEmbeddings()->count());
        $this->assertSame(2, $photo->bodyEmbeddings()->count());
    }

    public function test_uploaded_photo_is_auto_queued_when_enabled(): void
    {
        config(['services.ml_process.auto_index_on_upload' => true]);
        config(['services.image_variants.enabled' => true]);

        Bus::fake();

        $event = Event::create([
            'name' => 'Auto Queue Event',
            'slug' => 'auto-queue-event',
        ]);

        $photo = EventPhoto::create([
            'event_id' => $event->id,
            'storage_path' => 'event-photos/runner.jpg',
            'status' => 'uploaded',
        ]);

        Bus::assertDispatched(IndexEventPhoto::class);

        $this->assertSame('queued', $photo->refresh()->status);
        $this->assertDatabaseHas('photo_indexing_jobs', [
            'event_photo_id' => $photo->id,
            'event_id' => $event->id,
            'status' => 'queued',
        ]);
    }
}
