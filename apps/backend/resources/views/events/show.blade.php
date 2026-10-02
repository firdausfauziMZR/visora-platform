<x-layouts.app :title="$event->name">
    <section class="event-shell">
        <div class="event-hero">
            <div>
                <h1>{{ $event->name }}</h1>
                <p>{{ $event->venue ?: 'Event photo search' }}</p>

                <div class="event-meta" aria-label="Event summary">
                    <span class="pill">{{ $event->photos_count }} photos</span>
                    <span class="pill">{{ $event->face_embeddings_count }} faces indexed</span>
                    @if ($event->starts_at)
                        <span class="pill">{{ $event->starts_at->format('M j, Y') }}</span>
                    @endif
                </div>
            </div>

            <form class="search-card" method="POST" action="{{ route('events.search', $event) }}" enctype="multipart/form-data">
                @csrf
                <div>
                    <h2>Find Your Photos</h2>
                    <p>Upload a selfie to search this event gallery.</p>
                </div>

                <div class="field">
                    <label for="selfie">Selfie</label>
                    <input id="selfie" name="selfie" type="file" accept="image/*" required>
                </div>

                <button class="button" type="submit">Search Photos</button>
            </form>
        </div>

        <section class="panel">
            <h2>Gallery</h2>

            @if ($galleryPhotos->isEmpty())
                <div class="empty compact">No photos have been uploaded for this event yet.</div>
            @else
                <div class="gallery-grid">
                    @foreach ($galleryPhotos as $photo)
                        <a class="gallery-card" href="{{ route('photos.preview', [$event, $photo]) }}" target="_blank" rel="noreferrer">
                            <img src="{{ route('photos.preview', [$event, $photo]) }}" alt="{{ $photo->original_filename ?: 'Event photo '.$photo->id }}">
                            <span class="gallery-card-info">
                                <span class="gallery-card-name">{{ $photo->original_filename ?: 'Photo '.$photo->id }}</span>
                                <span class="badge">{{ $photo->faces_detected ?? 0 }} faces</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </section>
</x-layouts.app>
