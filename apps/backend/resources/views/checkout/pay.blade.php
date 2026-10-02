<x-layouts.app title="Complete Payment">
    <section style="margin-bottom: 22px;">
        <h1>Complete Payment</h1>
        <p>Order {{ $orderReference }} is waiting for payment before downloads are unlocked.</p>
    </section>

    <section class="panel" style="margin-bottom: 20px;">
        <h2>Order Summary</h2>
        <div class="download-list">
            @foreach ($unlocks as $unlock)
                @php($photo = $unlock->photo)
                @php($package = \App\Support\PhotoDownloadPackages::get($unlock->package_code))
                <div class="download-row">
                    <img class="download-thumb" src="{{ route('photos.preview', [$event, $photo, 'variant' => 'thumbnail']) }}" alt="{{ $photo->original_filename ?: 'Event photo' }}">
                    <div>
                        <strong>{{ $photo->original_filename ?: 'Photo '.$photo->id }}</strong>
                        <div class="muted">{{ $package['label'] }} · {{ \App\Support\PhotoDownloadPackages::formatPrice($unlock->price_cents) }}</div>
                    </div>
                    <strong>{{ \App\Support\PhotoDownloadPackages::formatPrice($unlock->price_cents) }}</strong>
                </div>
            @endforeach
        </div>

        <div class="event-row" style="margin-top: 18px;">
            <strong>Total</strong>
            <strong>{{ \App\Support\PhotoDownloadPackages::formatPrice($unlocks->sum('price_cents')) }}</strong>
        </div>
    </section>

    @if (config('services.photo_checkout.payment_driver') === 'local')
        <form method="POST" action="{{ route('checkout.local.confirm', [$event, $orderReference]) }}">
            @csrf
            <button class="button" type="submit">Simulate Payment</button>
            <a class="button secondary" href="{{ route('events.show', $event) }}">Cancel</a>
        </form>
    @else
        <div class="panel">
            Payment is still pending. Return to checkout from the previous payment page or try again.
        </div>
    @endif
</x-layouts.app>
