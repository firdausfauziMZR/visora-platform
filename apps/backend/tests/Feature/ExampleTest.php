<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_guest_can_open_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Find every photo you are in.')
            ->assertSee('Register now')
            ->assertSee('Log in');
    }

    public function test_authenticated_customer_is_redirected_from_landing_page_to_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirect(route('customer.dashboard'));
    }

    public function test_login_page_uses_form_register_action_without_guest_nav_links(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('>Register<', false)
            ->assertDontSee('href="'.route('login').'">Log in</a>', false);
    }

    public function test_register_page_requires_image_use_consent(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Image use permission')
            ->assertSee('Visora will not sell my images');

        $this->from(route('register'))
            ->post(route('register.store'), [
                'name' => 'Customer One',
                'email' => 'customer@example.test',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('image_use_consent');
    }

    public function test_customer_registration_stores_image_use_consent(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Customer One',
            'email' => 'customer@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'image_use_consent' => '1',
        ])
            ->assertRedirect(route('customer.dashboard'));

        $user = User::query()->where('email', 'customer@example.test')->firstOrFail();

        $this->assertNotNull($user->image_use_consent_at);
        $this->assertSame('2026-06-10', $user->image_use_consent_version);
    }

    public function test_authenticated_customer_can_open_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Your Events')
            ->assertSee('Open account menu')
            ->assertDontSee('My Galleries')
            ->assertDontSee('>Dashboard<', false)
            ->assertDontSee('>Admin<', false);
    }

    public function test_customer_cannot_open_admin_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_user_can_open_admin_panel(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin')
            ->assertOk();
    }

    public function test_admin_user_sees_admin_navigation_link(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Admin');
    }

    public function test_admin_dashboard_lists_active_events_without_code_form(): void
    {
        Event::create([
            'name' => 'Admin Visible Event',
            'slug' => 'admin-visible-event',
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Admin Preview')
            ->assertSee('Active Events')
            ->assertSee('Admin Visible Event')
            ->assertDontSee('Enter Event Code');
    }
}
