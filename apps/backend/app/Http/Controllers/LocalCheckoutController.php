<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Services\LunarPhotoCheckout;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocalCheckoutController extends Controller
{
    public function store(Request $request, Event $event, LunarPhotoCheckout $checkout): View
    {
        abort_unless($event->is_active, 404);

        $validated = $request->validate([
            'photo_ids' => ['required', 'array', 'min:1'],
            'photo_ids.*' => ['integer'],
            'customer_email' => ['nullable', 'email'],
        ]);

        $photos = EventPhoto::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $validated['photo_ids'])
            ->where('status', 'indexed')
            ->get();

        abort_if($photos->isEmpty(), 422);

        $order = $checkout->checkout(
            event: $event,
            photos: $photos,
            customerEmail: $validated['customer_email'] ?? null,
        );

        return view('checkout.complete', [
            'event' => $event,
            'photos' => $photos,
            'orderReference' => $order->reference,
            'order' => $order,
        ]);
    }
}
