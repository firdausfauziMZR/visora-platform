<x-layouts.app :title="$event->name.' Matches'">
    <section style="margin-bottom: 22px;">
        <h1>Matches</h1>
        <p>{{ $facesDetected }} face(s) detected in your selfie.</p>
    </section>

    @if ($matches->isEmpty())
        <div class="empty">
            No matching photos were found for this event.
            <div style="margin-top: 14px;">
                <a class="button secondary" href="{{ route('events.show', $event) }}">Try Another Selfie</a>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('checkout.local', $event) }}">
            @csrf
            <input type="hidden" name="search_reference" value="{{ $searchReference }}">

            <div class="field" style="max-width: 420px;">
                <label for="customer_email">Email for downloads</label>
                <input id="customer_email" name="customer_email" type="email" placeholder="you@example.com">
            </div>

            <div class="grid" style="margin: 18px 0;">
                @foreach ($matches as $match)
                    @php($photo = $match['photo'])
                    <label class="photo-card">
                        <img src="{{ route('photos.preview', [$event, $photo]) }}" alt="{{ $photo->original_filename }}">
                        <span class="photo-card-body">
                            <span>
                                <input type="checkbox" name="photo_ids[]" value="{{ $photo->id }}" checked>
                                {{ $photo->original_filename ?: 'Photo '.$photo->id }}
                            </span>
                            <span class="score">Cosine distance: {{ number_format($match['cosine_distance'], 6) }}</span>
                        </span>
                    </label>
                @endforeach
            </div>

            <button class="button" type="submit">Unlock Selected Photos</button>
            <a class="button secondary" href="{{ route('events.show', $event) }}">Search Again</a>
        </form>
    @endif
</x-layouts.app>
