<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\User;
use App\Services\PgvectorFaceSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Lunar\Models\Customer;
use Tests\TestCase;

class CustomerPhotoSearchFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_search_matches_unlock_and_download_photo(): void
    {
        Storage::fake('local');
        $user = User::factory()->create([
            'email' => 'customer@example.test',
        ]);

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

        $event->approvedUsers()->attach($user->id, ['approved_at' => now()]);

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

        $searchResponse = $this->actingAs($user)->post(route('events.search', $event), [
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
        ]);

        $searchResponse
            ->assertOk()
            ->assertSee('Matches')
            ->assertSee('match.jpg')
            ->assertSee('View details')
            ->assertSee('Package for selected photos')
            ->assertSee('Choose one package, then select the photos you want to unlock.')
            ->assertSee('Review this matched photo before selecting it for checkout.');

        $checkoutResponse = $this->actingAs($user)->post(route('checkout.local', $event), [
            'photo_ids' => [$photo->id],
        ]);

        $checkoutResponse
            ->assertRedirect();

        $orderReference = \Lunar\Models\Order::query()->where('user_id', $user->id)->firstOrFail()->reference;

        $checkoutResponse->assertRedirect(route('checkout.local.pay', [$event, $orderReference]));

        $this->assertDatabaseHas('download_unlocks', [
            'event_photo_id' => $photo->id,
            'user_id' => $user->id,
            'customer_email' => 'customer@example.test',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('No photos have been unlocked yet.');

        $this->actingAs($user)
            ->get(route('checkout.local.pay', [$event, $orderReference]))
            ->assertOk()
            ->assertSee('Complete Payment')
            ->assertSee('match.jpg');

        $this->actingAs($user)
            ->post(route('checkout.local.confirm', [$event, $orderReference]))
            ->assertRedirect(route('checkout.local.complete', [$event, $orderReference]));

        $checkoutCompleteResponse = $this->actingAs($user)
            ->get(route('checkout.local.complete', [$event, $orderReference]));

        $checkoutCompleteResponse
            ->assertOk()
            ->assertSee('Downloads Unlocked')
            ->assertSee('match.jpg')
            ->assertSee('This photo is ready to download from order');

        $this->assertDatabaseHas('download_unlocks', [
            'event_photo_id' => $photo->id,
            'user_id' => $user->id,
            'customer_email' => 'customer@example.test',
            'status' => 'unlocked',
        ]);

        $this->assertDatabaseHas('lunar_orders', [
            'user_id' => $user->id,
            'status' => 'payment-received',
            'currency_code' => 'USD',
            'sub_total' => 1000,
            'total' => 1000,
        ]);

        $customer = Customer::query()->where('account_ref', 'customer@example.test')->firstOrFail();

        $this->assertTrue($user->customers()->whereKey($customer)->exists());
        $this->assertDatabaseHas('lunar_orders', [
            'user_id' => $user->id,
            'customer_id' => $customer->id,
        ]);

        $this->assertDatabaseHas('lunar_order_lines', [
            'purchasable_type' => EventPhoto::class,
            'purchasable_id' => $photo->id,
            'identifier' => 'VISORA-PHOTO-'.$photo->id,
            'unit_price' => 1000,
            'quantity' => 1,
        ]);
    }

    public function test_customer_cannot_open_unapproved_event(): void
    {
        $user = User::factory()->create();
        $event = Event::create([
            'name' => 'Private Event',
            'slug' => 'private-event',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('events.show', $event))
            ->assertForbidden();
    }

    public function test_customer_can_unlock_a_selected_download_package(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'email' => 'customer@example.test',
        ]);
        $event = Event::create([
            'name' => 'Package Event',
            'slug' => 'package-event',
            'is_active' => true,
        ]);

        Storage::disk('local')->put('event-photos/original.jpg', 'original-photo-bytes');
        Storage::disk('local')->put('event-photos/social.jpg', 'social-photo-bytes');

        $photo = EventPhoto::create([
            'event_id' => $event->id,
            'original_filename' => 'package.jpg',
            'storage_disk' => 'local',
            'storage_path' => 'event-photos/original.jpg',
            'mime_type' => 'image/jpeg',
            'status' => 'indexed',
            'metadata' => [
                'variants' => [
                    'social_og' => [
                        'path' => 'event-photos/social.jpg',
                    ],
                ],
            ],
        ]);

        $event->approvedUsers()->attach($user->id, ['approved_at' => now()]);

        $response = $this->actingAs($user)->post(route('checkout.local', $event), [
            'photo_ids' => [$photo->id],
            'packages' => [
                $photo->id => 'social',
            ],
        ]);

        $orderReference = \Lunar\Models\Order::query()->where('user_id', $user->id)->firstOrFail()->reference;

        $response->assertRedirect(route('checkout.local.pay', [$event, $orderReference]));

        $this->actingAs($user)
            ->get(route('checkout.local.pay', [$event, $orderReference]))
            ->assertOk()
            ->assertSee('Social Crop')
            ->assertSee('USD 8.00');

        $this->actingAs($user)
            ->post(route('checkout.local.confirm', [$event, $orderReference]))
            ->assertRedirect(route('checkout.local.complete', [$event, $orderReference]));

        $this->assertDatabaseHas('download_unlocks', [
            'event_photo_id' => $photo->id,
            'user_id' => $user->id,
            'package_code' => 'social',
            'download_variant' => 'social_og',
            'price_cents' => 800,
            'status' => 'unlocked',
        ]);

        $this->assertDatabaseHas('lunar_order_lines', [
            'purchasable_type' => EventPhoto::class,
            'purchasable_id' => $photo->id,
            'unit_price' => 800,
        ]);
    }
}
