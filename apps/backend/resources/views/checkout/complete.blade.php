<x-layouts.app title="Downloads Unlocked">
    <section style="margin-bottom: 22px;">
        <h1>Downloads Unlocked</h1>
        <p>Order {{ $orderReference }} unlocked {{ $unlocks->count() }} download(s).</p>
    </section>

    <div class="photo-results-grid">
        @foreach ($unlocks as $unlock)
            @php($photo = $unlock->photo)
            @php($package = \App\Support\PhotoDownloadPackages::get($unlock->package_code))
            <article class="photo-card">
                <button class="photo-card-preview" type="button" data-photo-modal-open="unlocked-photo-{{ $unlock->id }}" aria-label="View {{ $photo->original_filename ?: 'photo '.$photo->id }}">
                    <img src="{{ route('photos.preview', [$event, $photo]) }}" alt="{{ $photo->original_filename ?: 'Unlocked photo' }}">
                </button>
                <div class="photo-card-body">
                    <strong>{{ $photo->original_filename ?: 'Photo '.$photo->id }}</strong>
                    <span class="score">{{ $package['label'] }} · {{ \App\Support\PhotoDownloadPackages::formatPrice($unlock->price_cents) }}</span>
                    <a class="button" href="{{ route('photos.download', [$event, $photo, $orderReference, 'variant' => $unlock->download_variant]) }}">Download</a>
                </div>

                <dialog class="photo-modal" id="unlocked-photo-{{ $unlock->id }}" aria-labelledby="unlocked-photo-title-{{ $unlock->id }}">
                    <div class="photo-modal-layout">
                        <div class="photo-modal-image">
                            <img src="{{ route('photos.preview', [$event, $photo]) }}" alt="{{ $photo->original_filename ?: 'Unlocked photo' }}">
                        </div>

                        <div class="photo-modal-panel">
                            <div>
                                <span class="eyebrow">Unlocked photo</span>
                                <h2 id="unlocked-photo-title-{{ $unlock->id }}">{{ $photo->original_filename ?: 'Photo '.$photo->id }}</h2>
                                <p>This photo is ready to download from order {{ $orderReference }}.</p>
                            </div>

                            <div class="package-row">
                                <strong>{{ $package['label'] }} · {{ \App\Support\PhotoDownloadPackages::formatPrice($unlock->price_cents) }}</strong>
                                <span>{{ $package['description'] }}</span>
                            </div>

                            <div class="photo-modal-actions">
                                <button class="button secondary" type="button" data-photo-modal-close>Close</button>
                                <a class="button" href="{{ route('photos.download', [$event, $photo, $orderReference, 'variant' => $unlock->download_variant]) }}">Download</a>
                            </div>
                        </div>
                    </div>
                </dialog>
            </article>
        @endforeach
    </div>

    <script>
        (() => {
            document.querySelectorAll('[data-photo-modal-open]').forEach((button) => {
                button.addEventListener('click', () => {
                    document.getElementById(button.dataset.photoModalOpen)?.showModal();
                });
            });

            document.querySelectorAll('[data-photo-modal-close]').forEach((button) => {
                button.addEventListener('click', () => button.closest('dialog')?.close());
            });

            document.querySelectorAll('.photo-modal').forEach((modal) => {
                modal.addEventListener('click', (event) => {
                    if (event.target === modal) {
                        modal.close();
                    }
                });
            });
        })();
    </script>
</x-layouts.app>
