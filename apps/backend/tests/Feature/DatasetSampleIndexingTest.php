<?php

namespace Tests\Feature;

use App\Jobs\IndexEventPhoto;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Services\LocalMlProcess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatasetSampleIndexingTest extends TestCase
{
    use RefreshDatabase;

    public function test_lfw_and_wider_sample_images_can_flow_through_indexing_job(): void
    {
        $samplePaths = [
            'lfw' => base_path('../../datasets/samples/lfw/George_HW_Bush_0001.jpg'),
            'wider-face' => base_path('../../datasets/samples/wider-face/0_Parade_marchingband_1_1004.jpg'),
        ];

        foreach ($samplePaths as $label => $path) {
            if (! file_exists($path)) {
                $this->markTestSkipped("Dataset sample is missing: {$path}");
            }

            $this->assertNotFalse(getimagesize($path));

            Storage::fake('local');
            $this->mock(LocalMlProcess::class)
                ->shouldReceive('indexPhoto')
                ->once()
                ->andReturn([
                    'status' => 'indexed',
                    'event_id' => '1',
                    'photo_id' => '1',
                    'filename' => basename($path),
                    'faces_detected' => 1,
                    'embedding_status' => 'generated',
                    'body_embedding_status' => 'generated',
                    'body_embeddings_count' => 1,
                    'faces' => [
                        [
                            'box' => ['x' => 1, 'y' => 2, 'width' => 30, 'height' => 40],
                            'embedding' => array_fill(0, 128, 0.1),
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

            $storagePath = "event-photos/{$label}-".basename($path);
            Storage::disk('local')->put($storagePath, file_get_contents($path));

            $event = Event::create([
                'name' => "Dataset Smoke {$label}",
                'slug' => "dataset-smoke-{$label}",
            ]);

            $photo = EventPhoto::create([
                'event_id' => $event->id,
                'original_filename' => basename($path),
                'storage_disk' => 'local',
                'storage_path' => $storagePath,
                'status' => 'queued',
            ]);

            $indexingJob = $photo->indexingJobs()->create([
                'event_id' => $event->id,
                'status' => 'queued',
            ]);

            app()->call([new IndexEventPhoto($indexingJob), 'handle']);

            $this->assertSame('completed', $indexingJob->refresh()->status);
            $this->assertSame('indexed', $photo->refresh()->status);
            $this->assertSame(1, $photo->faceEmbeddings()->count());
            $this->assertSame(1, $photo->bodyEmbeddings()->count());
        }
    }
}
