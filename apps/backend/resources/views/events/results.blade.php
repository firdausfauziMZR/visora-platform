<x-layouts.app :title="$event->name.' Matches'">
    <section style="margin-bottom: 22px;">
        <h1>Matches</h1>
        <p>{{ $facesDetected }} face(s) detected in your selfie. Showing only the strongest private-gallery matches.</p>
    </section>

    @if ($matches->isEmpty())
        <div class="empty">
            No matching photos were found for this event.
            <div style="margin-top: 14px;">
                <a class="button secondary" href="{{ route('events.show', $event) }}">Try Another Selfie</a>
            </div>
        </div>
    @else
        @php($packages = \App\Support\PhotoDownloadPackages::all())
        <form method="POST" action="{{ route('checkout.local', $event) }}">
            @csrf
            <input type="hidden" name="search_reference" value="{{ $searchReference }}">

            <div class="field" style="max-width: 420px;">
                <label for="customer_email">Email for downloads</label>
                <input id="customer_email" name="customer_email" type="email" value="{{ old('customer_email', auth()->user()?->email) }}" placeholder="you@example.com">
            </div>

            @php($defaultPackage = \App\Support\PhotoDownloadPackages::defaultCode())
            @php($defaultPackageData = $packages[$defaultPackage])
            <div class="panel package-checkout-panel">
                <div>
                    <h2>Package for selected photos</h2>
                    <p>Choose one package, then select the photos you want to unlock.</p>
                </div>

                <div class="package-choice-list" role="radiogroup" aria-label="Download package">
                    @foreach ($packages as $code => $package)
                        <label class="package-choice">
                            <input
                                type="radio"
                                name="selected_package"
                                value="{{ $code }}"
                                data-package-choice
                                data-summary="{{ $package['label'] }} - {{ \App\Support\PhotoDownloadPackages::formatPrice($package['price_cents']) }}"
                                @checked($code === $defaultPackage)
                            >
                            <span>
                                <strong>{{ $package['label'] }} · {{ \App\Support\PhotoDownloadPackages::formatPrice($package['price_cents']) }}</strong>
                                <small>{{ $package['description'] }}</small>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="photo-results-grid">
                @foreach ($matches as $match)
                    @php($photo = $match['photo'])
                    <article class="photo-card">
                        <button class="photo-card-preview" type="button" data-photo-modal-open="match-photo-{{ $photo->id }}" aria-label="View {{ $photo->original_filename ?: 'photo '.$photo->id }} details">
                            <img src="{{ route('photos.preview', [$event, $photo, 'variant' => 'thumbnail']) }}" alt="{{ $photo->original_filename ?: 'Matched event photo' }}">
                        </button>
                        <div class="photo-card-body">
                            <label class="inline-field photo-select-row">
                                <input type="checkbox" name="photo_ids[]" value="{{ $photo->id }}">
                                <span>{{ $photo->original_filename ?: 'Photo '.$photo->id }}</span>
                            </label>
                            <input type="hidden" name="packages[{{ $photo->id }}]" value="{{ $defaultPackage }}" data-package-input="{{ $photo->id }}">
                            <span class="score" data-package-summary="{{ $photo->id }}">
                                {{ $defaultPackageData['label'] }} - {{ \App\Support\PhotoDownloadPackages::formatPrice($defaultPackageData['price_cents']) }}
                            </span>
                            <button class="button secondary compact-button" type="button" data-photo-modal-open="match-photo-{{ $photo->id }}">View details</button>
                        </div>

                        <dialog class="photo-modal" id="match-photo-{{ $photo->id }}" aria-labelledby="match-photo-title-{{ $photo->id }}">
                            <div class="photo-modal-layout">
                                <div class="photo-modal-image">
                                    <img src="{{ route('photos.preview', [$event, $photo]) }}" alt="{{ $photo->original_filename ?: 'Matched event photo' }}">
                                </div>

                                <div class="photo-modal-panel">
                                    <div>
                                        <span class="eyebrow">Matched photo</span>
                                        <h2 id="match-photo-title-{{ $photo->id }}">{{ $photo->original_filename ?: 'Photo '.$photo->id }}</h2>
                                        <p>Review this matched photo before selecting it for checkout.</p>
                                    </div>

                                    <div class="metric">
                                        <span class="metric-value">{{ number_format($match['cosine_distance'], 4) }}</span>
                                        <span class="metric-label">Cosine distance</span>
                                    </div>

                                    <div class="photo-modal-actions">
                                        <button class="button secondary" type="button" data-photo-modal-close>Done</button>
                                    </div>
                                </div>
                            </div>
                        </dialog>
                    </article>
                @endforeach
            </div>

            <button class="button" type="submit">Unlock Selected Photos</button>
            <a class="button secondary" href="{{ route('events.show', $event) }}">Search Again</a>
        </form>

        <script>
            (() => {
                const openButtons = document.querySelectorAll('[data-photo-modal-open]');
                const closeButtons = document.querySelectorAll('[data-photo-modal-close]');
                const packageChoices = document.querySelectorAll('[data-package-choice]');

                openButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const modal = document.getElementById(button.dataset.photoModalOpen);
                        modal?.showModal();
                    });
                });

                closeButtons.forEach((button) => {
                    button.addEventListener('click', () => button.closest('dialog')?.close());
                });

                packageChoices.forEach((choice) => {
                    choice.addEventListener('change', () => {
                        document.querySelectorAll('[data-package-input]').forEach((input) => {
                            input.value = choice.value;
                        });

                        document.querySelectorAll('[data-package-summary]').forEach((summary) => {
                            if (choice.dataset.summary) {
                                summary.textContent = choice.dataset.summary;
                            }
                        });
                    });
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
    @endif
</x-layouts.app>
