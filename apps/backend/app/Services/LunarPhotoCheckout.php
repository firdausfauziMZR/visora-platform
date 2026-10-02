<?php

namespace App\Services;

use App\Models\DownloadUnlock;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\User;
use App\Support\PhotoDownloadPackages;
use Illuminate\Support\Collection;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\Price;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxRateAmount;
use Lunar\Models\TaxZone;
use Spatie\LaravelBlink\BlinkFacade as Blink;

class LunarPhotoCheckout
{
    /**
     * @param  Collection<int, EventPhoto>  $photos
     * @param  array<int, string>  $packageSelections
     */
    public function checkout(Event $event, Collection $photos, ?string $customerEmail = null, ?User $user = null, array $packageSelections = []): Order
    {
        $currency = $this->currency();
        $channel = $this->channel();
        $this->taxSetup();
        $customer = $user ? app(LunarCustomerSync::class)->sync($user) : null;

        $cart = Cart::create([
            'currency_id' => $currency->id,
            'channel_id' => $channel->id,
            'meta' => [
                'visora_event_id' => $event->id,
                'customer_email' => $customerEmail,
                'checkout_mode' => config('services.photo_checkout.payment_driver'),
            ],
        ]);

        if ($user) {
            $cart->associate($user, policy: 'override');
        }

        if ($customer) {
            $cart->setCustomer($customer);
        }

        foreach ($photos as $photo) {
            $packageCode = $packageSelections[$photo->id] ?? PhotoDownloadPackages::defaultCode();
            $package = PhotoDownloadPackages::get($packageCode);

            $this->ensurePrice($photo, $currency, $package['price_cents']);

            $cart->add($photo, quantity: 1, meta: [
                'event_id' => $event->id,
                'event_photo_id' => $photo->id,
                'original_filename' => $photo->original_filename,
                'package_code' => $packageCode,
                'download_variant' => $package['variant'],
                'package_label' => $package['label'],
            ]);
        }

        $cart->setBillingAddress([
            'country_id' => $this->country()->id,
            'first_name' => 'Visora',
            'last_name' => 'Customer',
            'line_one' => 'Local checkout',
            'city' => 'Local',
            'postcode' => '00000',
            'contact_email' => $customerEmail,
        ]);

        $order = $cart->createOrder(allowMultipleOrders: true);

        foreach ($photos as $photo) {
            $packageCode = $packageSelections[$photo->id] ?? PhotoDownloadPackages::defaultCode();
            $package = PhotoDownloadPackages::get($packageCode);

            DownloadUnlock::create([
                'event_photo_id' => $photo->id,
                'user_id' => $user?->id,
                'order_reference' => $order->reference,
                'customer_email' => $customerEmail ?: $user?->email,
                'package_code' => $packageCode,
                'download_variant' => $package['variant'],
                'price_cents' => $package['price_cents'],
                'status' => 'pending',
                'unlocked_at' => null,
            ]);
        }

        return $order->refresh();
    }

    public function complete(Order $order): Order
    {
        if ($order->status === 'payment-received') {
            return $order->refresh();
        }

        $order->update([
            'status' => 'payment-received',
            'placed_at' => now(),
        ]);

        DownloadUnlock::query()
            ->where('order_reference', $order->reference)
            ->where('status', 'pending')
            ->update([
                'status' => 'unlocked',
                'unlocked_at' => now(),
            ]);

        return $order->refresh();
    }

    public function ensurePrice(EventPhoto $photo, Currency $currency, ?int $priceCents = null): Price
    {
        $this->taxSetup();

        return $photo->prices()->updateOrCreate(
            [
                'currency_id' => $currency->id,
                'customer_group_id' => null,
                'min_quantity' => 1,
            ],
            [
                'price' => $priceCents ?? (int) config('services.photo_checkout.price_cents'),
            ],
        );
    }

    private function currency(): Currency
    {
        $currency = Currency::getDefault();

        if ($currency) {
            return $currency;
        }

        return Currency::create([
            'code' => (string) config('services.photo_checkout.currency_code'),
            'name' => (string) config('services.photo_checkout.currency_code'),
            'exchange_rate' => 1,
            'decimal_places' => 2,
            'enabled' => true,
            'default' => true,
        ]);
    }

    private function channel(): Channel
    {
        $channel = Channel::getDefault();

        if ($channel) {
            return $channel;
        }

        return Channel::create([
            'name' => 'Webstore',
            'handle' => 'webstore',
            'url' => config('app.url'),
            'default' => true,
        ]);
    }

    private function country(): Country
    {
        return Country::query()->where('iso3', 'USA')->first()
            ?: Country::query()->first()
            ?: Country::create([
                'name' => 'United States',
                'iso2' => 'US',
                'iso3' => 'USA',
                'phonecode' => '+1',
                'capital' => 'Washington',
                'currency' => 'USD',
                'native' => 'United States',
                'emoji' => 'US',
                'emoji_u' => 'U+1F1FA U+1F1F8',
            ]);
    }

    private function taxSetup(): TaxClass
    {
        $taxClass = TaxClass::query()->where('default', true)->first() ?: TaxClass::create([
            'name' => 'Default Tax Class',
            'default' => true,
        ]);

        $taxZone = TaxZone::query()->where('default', true)->first() ?: TaxZone::create([
            'name' => 'Default Tax Zone',
            'zone_type' => 'country',
            'price_display' => 'tax_exclusive',
            'active' => true,
            'default' => true,
        ]);

        $taxRate = TaxRate::query()->firstOrCreate(
            [
                'tax_zone_id' => $taxZone->id,
                'name' => 'No Tax',
            ],
            [
                'priority' => 1,
            ],
        );

        TaxRateAmount::query()->firstOrCreate(
            [
                'tax_class_id' => $taxClass->id,
                'tax_rate_id' => $taxRate->id,
            ],
            [
                'percentage' => 0,
            ],
        );

        Blink::forget('lunar_default_lunar_models_tax_class');
        Blink::forget('lunar_default_lunar_models_tax_zone');

        return $taxClass;
    }
}
