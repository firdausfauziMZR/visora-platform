<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventAccessCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_unlock_event_with_access_code(): void
    {
        $user = User::factory()->create();
        $event = Event::create([
            'name' => 'Private Gallery',
            'slug' => 'private-gallery',
            'access_code' => 'VIP-2026',
            'is_active' => true,
        ]);

        $this->assertFalse($user->canAccessEvent($event));

        $this->actingAs($user)
            ->post(route('event-access-codes.store'), [
                'access_code' => ' vip 2026 ',
            ])
            ->assertRedirect(route('events.show', $event));

        $this->assertTrue($user->fresh()->canAccessEvent($event));
        $this->assertDatabaseHas('event_user_access', [
            'event_id' => $event->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_customer_can_unlock_event_with_qr_landing_link(): void
    {
        $user = User::factory()->create();
        $event = Event::create([
            'name' => 'QR Gallery',
            'slug' => 'qr-gallery',
            'access_code' => 'QR2026',
            'access_mode' => Event::ACCESS_MODE_CODE_GRANTS_ACCESS,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('event-access-codes.show', $event->access_code))
            ->assertRedirect(route('events.show', $event));

        $this->assertTrue($user->fresh()->canAccessEvent($event));
    }

    public function test_code_can_create_pending_access_request(): void
    {
        $user = User::factory()->create();
        $event = Event::create([
            'name' => 'Approval Required Gallery',
            'slug' => 'approval-required-gallery',
            'access_code' => 'WAIT2026',
            'access_mode' => Event::ACCESS_MODE_CODE_CREATES_REQUEST,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('event-access-codes.store'), [
                'access_code' => 'WAIT2026',
            ])
            ->assertRedirect(route('customer.dashboard'));

        $this->assertFalse($user->fresh()->canAccessEvent($event));
        $this->assertTrue($event->pendingAccessRequests()->whereKey($user->id)->exists());
    }

    public function test_manual_access_mode_does_not_create_access_request(): void
    {
        $user = User::factory()->create();
        $event = Event::create([
            'name' => 'Manual Gallery',
            'slug' => 'manual-gallery',
            'access_code' => 'MANUAL2026',
            'access_mode' => Event::ACCESS_MODE_MANUAL,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('event-access-codes.store'), [
                'access_code' => 'MANUAL2026',
            ])
            ->assertRedirect(route('customer.dashboard'))
            ->assertSessionHasErrors('access_code');

        $this->assertFalse($user->fresh()->canAccessEvent($event));
        $this->assertFalse($event->pendingAccessRequests()->whereKey($user->id)->exists());
    }

    public function test_admin_can_open_event_qr_svg(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $event = Event::create([
            'name' => 'QR Download Gallery',
            'slug' => 'qr-download-gallery',
            'access_code' => 'SVG2026',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('events.access-qr', $event))
            ->assertOk()
            ->assertHeader('content-type', 'image/svg+xml')
            ->assertSee('<svg', false);
    }

    public function test_customer_cannot_open_event_qr_svg(): void
    {
        $customer = User::factory()->create();
        $event = Event::create([
            'name' => 'Protected QR Gallery',
            'slug' => 'protected-qr-gallery',
            'access_code' => 'NOPE2026',
            'is_active' => true,
        ]);

        $this->actingAs($customer)
            ->get(route('events.access-qr', $event))
            ->assertForbidden();
    }

    public function test_customer_cannot_unlock_event_with_invalid_access_code(): void
    {
        $user = User::factory()->create();
        $event = Event::create([
            'name' => 'Private Gallery',
            'slug' => 'private-gallery',
            'access_code' => 'VIP2026',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->from(route('customer.dashboard'))
            ->post(route('event-access-codes.store'), [
                'access_code' => 'WRONG',
            ])
            ->assertRedirect(route('customer.dashboard'))
            ->assertSessionHasErrors('access_code');

        $this->assertFalse($user->fresh()->canAccessEvent($event));
    }

    public function test_event_access_code_is_generated_when_missing(): void
    {
        $event = Event::create([
            'name' => 'Generated Code Event',
            'slug' => 'generated-code-event',
            'is_active' => true,
        ]);

        $this->assertNotNull($event->access_code);
        $this->assertSame(8, strlen($event->access_code));
    }
}
