<?php

namespace App\Services;

use App\Models\Event;
use App\Models\DownloadUnlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Lunar\Models\Order;
use RuntimeException;

class PhotoPaymentGateway
{
    public function redirectToPayment(Event $event, Order $order): RedirectResponse
    {
        if ($this->usesStripe()) {
            return $this->redirectToStripe($event, $order);
        }

        return redirect()->route('checkout.local.pay', [$event, $order->reference]);
    }

    public function usesStripe(): bool
    {
        return config('services.photo_checkout.payment_driver') === 'stripe'
            && filled(config('services.stripe.secret'));
    }

    public function isStripeSessionPaid(string $sessionId): bool
    {
        $response = Http::withToken((string) config('services.stripe.secret'))
            ->get("https://api.stripe.com/v1/checkout/sessions/{$sessionId}");

        if ($response->failed()) {
            return false;
        }

        return $response->json('payment_status') === 'paid';
    }

    private function redirectToStripe(Event $event, Order $order): RedirectResponse
    {
        $unlocks = DownloadUnlock::query()
            ->with('photo')
            ->where('order_reference', $order->reference)
            ->where('status', 'pending')
            ->get();

        if ($unlocks->isEmpty()) {
            throw new RuntimeException('Cannot create a payment session without pending downloads.');
        }

        $lineItems = $unlocks
            ->values()
            ->map(fn (DownloadUnlock $unlock): array => [
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower((string) config('services.photo_checkout.currency_code')),
                    'unit_amount' => $unlock->price_cents,
                    'product_data' => [
                        'name' => ($unlock->photo?->original_filename ?: 'Event photo').' - '.$unlock->package_code,
                    ],
                ],
            ])
            ->all();

        $response = Http::asForm()
            ->withToken((string) config('services.stripe.secret'))
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'client_reference_id' => $order->reference,
                'customer_email' => data_get($order->meta, 'customer_email'),
                'success_url' => route('checkout.local.complete', [$event, $order->reference]).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('events.show', $event),
                'metadata' => [
                    'order_reference' => $order->reference,
                    'event_id' => (string) $event->id,
                    'user_id' => (string) $order->user_id,
                ],
                'line_items' => $lineItems,
            ]);

        if ($response->failed() || ! $response->json('url')) {
            throw new RuntimeException('Stripe Checkout session could not be created.');
        }

        $meta = $order->meta?->toArray() ?? [];
        $order->update([
            'meta' => [
                ...$meta,
                'stripe_checkout_session_id' => $response->json('id'),
            ],
        ]);

        return redirect()->away($response->json('url'));
    }
}
