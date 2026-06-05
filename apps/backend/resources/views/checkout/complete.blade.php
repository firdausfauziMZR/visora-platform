<x-layouts.app title="Downloads Unlocked">
    <section style="margin-bottom: 22px;">
        <h1>Downloads Unlocked</h1>
        <p>Order {{ $orderReference }} unlocked {{ $photos->count() }} photo(s).</p>
    </section>

    <div class="grid">
        @foreach ($photos as $photo)
            <article class="photo-card">
                <img src="{{ route('photos.preview', [$event, $photo]) }}" alt="{{ $photo->original_filename }}">
                <div class="photo-card-body">
                    <strong>{{ $photo->original_filename ?: 'Photo '.$photo->id }}</strong>
                    <a class="button" href="{{ route('photos.download', [$event, $photo, $orderReference]) }}">Download</a>
                </div>
            </article>
        @endforeach
    </div>
</x-layouts.app>
