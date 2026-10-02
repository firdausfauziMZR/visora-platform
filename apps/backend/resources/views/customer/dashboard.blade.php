<x-layouts.app title="Customer Dashboard">
    <section class="page-hero">
        <div>
            <span class="eyebrow">{{ auth()->user()?->is_admin ? 'Admin Preview' : 'Customer Dashboard' }}</span>
            <h1>Your Events</h1>
            <p>{{ auth()->user()?->is_admin ? 'Open any active event gallery without using an access code.' : 'Open approved event galleries, search by selfie, and return to unlocked downloads.' }}</p>
        </div>

        <div class="hero-summary" aria-label="Customer summary">
            <div class="summary-tile">
                <span class="summary-value">{{ $events->count() }}</span>
                <span class="summary-label">{{ auth()->user()?->is_admin ? 'Active events' : 'Approved events' }}</span>
            </div>
            <div class="summary-tile">
                <span class="summary-value">{{ $events->sum('photos_count') }}</span>
                <span class="summary-label">Available photos</span>
            </div>
            <div class="summary-tile">
                <span class="summary-value">{{ $downloads->count() }}</span>
                <span class="summary-label">Recent unlocks</span>
            </div>
        </div>
    </section>

    @if (session('status'))
        <div class="panel" style="margin-bottom: 18px;">{{ session('status') }}</div>
    @endif

    @unless (auth()->user()?->is_admin)
        <section class="panel" style="margin-bottom: 26px;">
            <form method="POST" action="{{ route('event-access-codes.store') }}">
                @csrf
                <h2>Enter Event Code</h2>
                <p>Use the code from your event organizer to unlock a private gallery.</p>

                <div class="field">
                    <label for="access_code">Event code</label>
                    <input id="access_code" name="access_code" type="text" value="{{ old('access_code') }}" autocomplete="off" required>
                </div>

                <button class="button" type="submit">Unlock Event</button>
            </form>
        </section>
    @endunless

    <section style="margin-bottom: 26px;">
        <h2>{{ auth()->user()?->is_admin ? 'Active Events' : 'Approved Events' }}</h2>

        @if ($events->isEmpty())
            <div class="empty">{{ auth()->user()?->is_admin ? 'No active events have been created yet.' : 'No events have been approved for your account yet.' }}</div>
        @else
            <div class="events-grid">
                @foreach ($events as $event)
                    <a class="event-card" href="{{ route('events.show', $event) }}">
                        <div class="event-card-main">
                            <h2>{{ $event->name }}</h2>
                            <div class="muted">{{ $event->venue ?: 'Event photo search' }}</div>
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
    </section>

    <section>
        <div class="section-heading">
            <h2>Recent Downloads</h2>
            <a class="button secondary compact-button" href="{{ route('customer.downloads') }}">View all</a>
        </div>

        @if ($downloads->isEmpty())
            <div class="empty">No photos have been unlocked yet.</div>
        @else
            <div class="download-list download-list-scroll">
                @foreach ($downloads as $download)
                    @php($photo = $download->photo)
                    @php($event = $photo?->event)
                    @if ($photo && $event)
                        <div class="download-row">
                            <img class="download-thumb" src="{{ route('photos.preview', [$event, $photo, 'variant' => 'thumbnail']) }}" alt="{{ $photo->original_filename ?: 'Event photo' }}">
                            <div>
                                <strong>{{ $photo->original_filename ?: 'Photo '.$photo->id }}</strong>
                                <div class="muted">{{ $event->name }} · {{ \App\Support\PhotoDownloadPackages::get($download->package_code)['label'] }}</div>
                            </div>
                            @if ($download->order_reference)
                                <a class="button secondary" href="{{ route('photos.download', [$event, $photo, $download->order_reference, 'variant' => $download->download_variant]) }}">Download</a>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
