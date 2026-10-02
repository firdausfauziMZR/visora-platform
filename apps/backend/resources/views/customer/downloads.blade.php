<x-layouts.app title="My Downloads">
    <section class="page-hero">
        <div>
            <span class="eyebrow">Unlocked Photos</span>
            <h1>My Downloads</h1>
            <p>Download photos you have unlocked from your approved event galleries.</p>
        </div>

        <div class="hero-summary" aria-label="Download summary">
            <div class="summary-tile">
                <span class="summary-value">{{ $downloads->count() }}</span>
                <span class="summary-label">Unlocked files</span>
            </div>
        </div>
    </section>

    @if ($downloads->isEmpty())
        <div class="empty">No photos have been unlocked yet.</div>
    @else
        <div class="download-list">
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
</x-layouts.app>
