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

        <section class="tabs" aria-label="Event tools">
            <div class="tab-controls" role="tablist" aria-label="Event sections">
                <button class="tab-button" type="button" role="tab" aria-selected="true" data-tab-target="gallery">Gallery</button>
                <button class="tab-button" type="button" role="tab" aria-selected="false" data-tab-target="search">Search</button>
                <button class="tab-button" type="button" role="tab" aria-selected="false" data-tab-target="details">Details</button>
            </div>

            <div class="tab-panel" role="tabpanel" data-tab-panel="gallery">
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
            </div>

            <div class="tab-panel" role="tabpanel" data-tab-panel="search" hidden>
                <form method="POST" action="{{ route('events.search', $event) }}" enctype="multipart/form-data">
                    @csrf
                    <h2>Search By Selfie</h2>
                    <p>Choose a clear selfie. Visora compares it with indexed faces from this event and returns matching photos.</p>

                    <div class="field">
                        <label for="selfie-tab">Selfie</label>
                        <input id="selfie-tab" name="selfie" type="file" accept="image/*" required>
                    </div>

                    <button class="button" type="submit">Search Photos</button>
                </form>
            </div>

            <div class="tab-panel" role="tabpanel" data-tab-panel="details" hidden>
                <div class="detail-grid">
                    <div class="metric">
                        <span class="metric-value">{{ $event->photos_count }}</span>
                        <span class="metric-label">Uploaded photos</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value">{{ $event->face_embeddings_count }}</span>
                        <span class="metric-label">Indexed faces</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value">{{ $galleryPhotos->count() }}</span>
                        <span class="metric-label">Gallery preview photos</span>
                    </div>
                </div>
            </div>
        </section>
    </section>

    <script>
        document.querySelectorAll('[data-tab-target]').forEach((button) => {
            button.addEventListener('click', () => {
                const target = button.dataset.tabTarget;

                document.querySelectorAll('[data-tab-target]').forEach((tab) => {
                    tab.setAttribute('aria-selected', String(tab === button));
                });

                document.querySelectorAll('[data-tab-panel]').forEach((panel) => {
                    panel.hidden = panel.dataset.tabPanel !== target;
                });
            });
        });
    </script>
</x-layouts.app>
