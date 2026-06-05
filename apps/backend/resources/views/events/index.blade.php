<x-layouts.app title="Visora Events">
    <section style="margin-bottom: 24px;">
        <h1>Events</h1>
        <p>Choose an active event, upload a selfie, and find matching event photos.</p>
    </section>

    @if ($events->isEmpty())
        <div class="empty">No active events are available yet.</div>
    @else
        <div class="grid">
            @foreach ($events as $event)
                <article class="panel event-row">
                    <div>
                        <h2>{{ $event->name }}</h2>
                        <div class="muted">
                            {{ $event->venue ?: 'No venue set' }} ·
                            {{ $event->photos_count }} photos ·
                            {{ $event->face_embeddings_count }} faces indexed
                        </div>
                    </div>
                    <a class="button secondary" href="{{ route('events.show', $event) }}">Open</a>
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.app>
