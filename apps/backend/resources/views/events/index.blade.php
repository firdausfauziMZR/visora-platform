<x-layouts.app title="Visora Events">
    <section class="page-hero">
        <div>
            <span class="eyebrow">Photo Search</span>
            <h1>Events</h1>
            <p>Choose an active event, upload a selfie, and find matching event photos.</p>
        </div>

        <div class="hero-summary" aria-label="Event summary">
            <div class="summary-tile">
                <span class="summary-value">{{ $events->count() }}</span>
                <span class="summary-label">Active events</span>
            </div>
            <div class="summary-tile">
                <span class="summary-value">{{ $events->sum('photos_count') }}</span>
                <span class="summary-label">Photos</span>
            </div>
            <div class="summary-tile">
                <span class="summary-value">{{ $events->sum('face_embeddings_count') }}</span>
                <span class="summary-label">Faces indexed</span>
            </div>
        </div>
    </section>

    @if ($events->isEmpty())
        <div class="empty">No active events are available yet.</div>
    @else
        <div class="events-grid">
            @foreach ($events as $event)
                <a class="event-card" href="{{ route('events.show', $event) }}">
                    <div class="event-card-main">
                        <h2>{{ $event->name }}</h2>
                        <div class="muted">{{ $event->venue ?: 'No venue set' }}</div>
                    </div>

                    <div class="event-card-footer">
                        <div class="event-card-stats">
                            <span class="event-stat">{{ $event->photos_count }} photos</span>
                            <span class="event-stat {{ $event->face_embeddings_count > 0 ? 'ready' : 'pending' }}">
                                {{ $event->face_embeddings_count }} faces indexed
                            </span>
                        </div>
                        <span class="event-open" aria-hidden="true">›</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
