<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Customer;
use Tests\TestCase;

class CustomerRegistrationCommerceSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_customer_account_creates_linked_commerce_customer(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Aina Hassan',
            'email' => 'aina@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'image_use_consent' => '1',
        ])->assertRedirect(route('customer.dashboard'));

        $customer = Customer::query()->where('account_ref', 'aina@example.test')->firstOrFail();

        $this->assertSame('Aina', $customer->first_name);
        $this->assertSame('Hassan', $customer->last_name);
        $this->assertTrue($customer->users()->where('email', 'aina@example.test')->exists());
    }

    public function test_existing_customer_account_syncs_when_dashboard_is_opened(): void
    {
        $user = User::factory()->create([
            'name' => 'Existing Customer',
            'email' => 'existing@example.test',
        ]);

        $this->assertDatabaseMissing('lunar_customers', [
            'account_ref' => 'existing@example.test',
        ]);

        $this->actingAs($user)
            ->get(route('customer.dashboard'))
            ->assertOk();

        $customer = Customer::query()->where('account_ref', 'existing@example.test')->firstOrFail();

        $this->assertTrue($customer->users()->whereKey($user)->exists());
    }
}
