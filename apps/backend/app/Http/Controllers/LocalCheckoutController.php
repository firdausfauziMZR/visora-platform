<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Services\LunarPhotoCheckout;
use App\Services\PhotoPaymentGateway;
use App\Support\PhotoDownloadPackages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Lunar\Models\Order;

class LocalCheckoutController extends Controller
{
    public function store(Request $request, Event $event, LunarPhotoCheckout $checkout, PhotoPaymentGateway $payments): RedirectResponse
    {
        abort_unless($event->is_active, 404);
        abort_unless($event->isAccessibleBy($request->user()), 403);

        $validated = $request->validate([
            'photo_ids' => ['required', 'array', 'min:1'],
            'photo_ids.*' => ['integer'],
            'packages' => ['nullable', 'array'],
            'packages.*' => ['nullable', 'string'],
            'customer_email' => ['nullable', 'email'],
        ]);

        $packageSelections = collect($validated['photo_ids'])
            ->mapWithKeys(function (int|string $photoId) use ($validated): array {
                $packageCode = data_get($validated, "packages.{$photoId}", PhotoDownloadPackages::defaultCode());

                if (! PhotoDownloadPackages::isValid($packageCode)) {
                    $packageCode = PhotoDownloadPackages::defaultCode();
                }

                return [(int) $photoId => $packageCode];
            })
            ->all();

        $photos = EventPhoto::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $validated['photo_ids'])
            ->where('status', 'indexed')
            ->get();

        abort_if($photos->isEmpty(), 422);

        $order = $checkout->checkout(
            event: $event,
            photos: $photos,
            customerEmail: $validated['customer_email'] ?? $request->user()->email,
            user: $request->user(),
            packageSelections: $packageSelections,
        );

        return $payments->redirectToPayment($event, $order);
    }

    public function pay(Request $request, Event $event, string $orderReference): View
    {
        $order = $this->orderForUser($request, $orderReference);
        abort_unless((int) data_get($order->meta, 'visora_event_id') === $event->id, 404);
        abort_if($order->status === 'payment-received', 404);

        return view('checkout.pay', [
            'event' => $event,
            'order' => $order,
            'orderReference' => $order->reference,
            'unlocks' => $this->unlocksForOrder($request, $order)->get(),
        ]);
    }

    public function confirmLocalPayment(Request $request, Event $event, string $orderReference, LunarPhotoCheckout $checkout): RedirectResponse
    {
        abort_unless(config('services.photo_checkout.payment_driver') === 'local', 404);

        $order = $this->orderForUser($request, $orderReference);
        abort_unless((int) data_get($order->meta, 'visora_event_id') === $event->id, 404);

        $checkout->complete($order);

        return redirect()->route('checkout.local.complete', [$event, $order->reference]);
    }

    public function complete(Request $request, Event $event, string $orderReference, LunarPhotoCheckout $checkout, PhotoPaymentGateway $payments): View|RedirectResponse
    {
        $order = $this->orderForUser($request, $orderReference);
        abort_unless((int) data_get($order->meta, 'visora_event_id') === $event->id, 404);

        if ($order->status !== 'payment-received') {
            $sessionId = $request->query('session_id');

            if (is_string($sessionId) && $payments->usesStripe() && $payments->isStripeSessionPaid($sessionId)) {
                $order = $checkout->complete($order);
            } else {
                return redirect()->route('checkout.local.pay', [$event, $order->reference]);
            }
        }

        return view('checkout.complete', [
            'event' => $event,
            'unlocks' => $this->unlocksForOrder($request, $order)
                ->where('status', 'unlocked')
                ->get(),
            'orderReference' => $order->reference,
            'order' => $order,
        ]);
    }

    private function orderForUser(Request $request, string $orderReference): Order
    {
        return Order::query()
            ->where('reference', $orderReference)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    private function unlocksForOrder(Request $request, Order $order)
    {
        return \App\Models\DownloadUnlock::query()
            ->with('photo')
            ->where('order_reference', $order->reference)
            ->where('user_id', $request->user()->id);
    }
}
