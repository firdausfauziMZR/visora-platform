<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Services\PgvectorFaceSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerPhotoSearchFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_search_matches_unlock_and_download_photo(): void
    {
        Storage::fake('local');

        $event = Event::create([
            'name' => 'Local Marathon',
            'slug' => 'local-marathon',
            'is_active' => true,
        ]);

        Storage::disk('local')->put('event-photos/match.jpg', 'fake-photo-bytes');

        $photo = EventPhoto::create([
            'event_id' => $event->id,
            'original_filename' => 'match.jpg',
            'storage_disk' => 'local',
            'storage_path' => 'event-photos/match.jpg',
            'mime_type' => 'image/jpeg',
            'status' => 'indexed',
            'faces_detected' => 1,
            'indexed_at' => now(),
        ]);

        $this->mock(PgvectorFaceSearch::class)
            ->shouldReceive('searchSelfie')
            ->once()
            ->andReturn([
                'status' => 'searched',
                'event_id' => (string) $event->id,
                'filename' => 'selfie.jpg',
                'faces_detected' => 1,
                'matches' => [
                    [
                        'photo_id' => (string) $photo->id,
                        'cosine_distance' => 0.01,
                    ],
                ],
            ]);

        $searchResponse = $this->post(route('events.search', $event), [
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
        ]);

        $searchResponse
            ->assertOk()
            ->assertSee('Matches')
            ->assertSee('match.jpg');

        $checkoutResponse = $this->post(route('checkout.local', $event), [
            'photo_ids' => [$photo->id],
            'customer_email' => 'customer@example.test',
        ]);

        $checkoutResponse
            ->assertOk()
            ->assertSee('Downloads Unlocked')
            ->assertSee('match.jpg');

        $this->assertDatabaseHas('download_unlocks', [
            'event_photo_id' => $photo->id,
            'customer_email' => 'customer@example.test',
            'status' => 'unlocked',
        ]);

        $this->assertDatabaseHas('lunar_orders', [
            'status' => 'payment-received',
            'currency_code' => 'USD',
            'sub_total' => 1000,
            'total' => 1000,
        ]);

        $this->assertDatabaseHas('lunar_order_lines', [
            'purchasable_type' => EventPhoto::class,
            'purchasable_id' => $photo->id,
            'identifier' => 'VISORA-PHOTO-'.$photo->id,
            'unit_price' => 1000,
            'quantity' => 1,
        ]);
    }
}
