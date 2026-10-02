<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\VisoraOperationsOverview;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Navigation\MenuItem;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Support\HtmlString;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->userMenuItems([
                'main-site' => MenuItem::make()
                    ->label('Main Site')
                    ->icon('heroicon-o-home')
                    ->url(fn (): string => route('events.index')),
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString(<<<'HTML'
                    <style>
                        .visora-batch-photo-upload,
                        .visora-batch-photo-upload > div,
                        .visora-batch-photo-upload .filepond--root {
                            width: 100% !important;
                            max-width: none !important;
                        }

                        .visora-batch-photo-upload .visora-batch-photo-upload__stats {
                            display: flex;
                            gap: 0.375rem;
                            margin-bottom: 0.75rem;
                            flex-wrap: wrap;
                            font-size: 0.8125rem;
                            line-height: 1.25rem;
                        }

                        .visora-batch-photo-upload .visora-batch-photo-upload__stat {
                            border-radius: 0.375rem;
                            background: rgba(255, 255, 255, 0.07);
                            padding: 0.25rem 0.625rem;
                            color: rgba(255, 255, 255, 0.72);
                        }

                        .visora-batch-photo-upload .filepond--root {
                            width: 100% !important;
                        }

                        .visora-batch-photo-upload .filepond--panel-root {
                            border-radius: 0.875rem !important;
                        }

                        .visora-batch-photo-upload .filepond--drop-label {
                            min-height: 5.5rem !important;
                        }

                        .visora-batch-photo-upload .filepond--list-scroller {
                            display: none !important;
                        }

                        .visora-batch-photo-upload .filepond--image-preview-wrapper,
                        .visora-batch-photo-upload .filepond--image-preview,
                        .visora-batch-photo-upload .filepond--image-preview-overlay,
                        .visora-batch-photo-upload .filepond--file-poster-wrapper {
                            display: none !important;
                        }

                        .visora-batch-photo-upload .visora-batch-photo-upload__queue {
                            margin-top: 0.75rem;
                            max-height: min(20rem, 42vh);
                            overflow-y: auto;
                            overflow-x: hidden;
                            scrollbar-gutter: stable;
                        }

                        .visora-batch-photo-upload .visora-batch-photo-upload__queue[hidden] {
                            display: none !important;
                        }

                        .visora-batch-photo-upload .visora-batch-photo-upload__row {
                            display: grid;
                            grid-template-columns: minmax(0, 1fr) auto;
                            gap: 0.75rem;
                            align-items: center;
                            min-height: 3.25rem;
                            margin-bottom: 0.5rem;
                            border-radius: 0.5rem;
                            background: rgba(255, 255, 255, 0.10);
                            padding: 0.75rem 0.875rem;
                        }

                        .visora-batch-photo-upload .visora-batch-photo-upload__row:last-child {
                            margin-bottom: 0;
                        }

                        .visora-batch-photo-upload .visora-batch-photo-upload__name {
                            overflow: hidden;
                            text-overflow: ellipsis;
                            white-space: nowrap;
                            color: rgba(255, 255, 255, 0.92);
                        }

                        .visora-batch-photo-upload .visora-batch-photo-upload__meta {
                            font-size: 0.75rem;
                            color: rgba(255, 255, 255, 0.56);
                        }

                        .visora-batch-photo-upload .visora-batch-photo-upload__badge {
                            border-radius: 9999px;
                            padding: 0.125rem 0.5rem;
                            font-size: 0.75rem;
                            line-height: 1rem;
                            background: rgba(255, 255, 255, 0.08);
                            color: rgba(255, 255, 255, 0.78);
                        }

                        .visora-batch-photo-upload .visora-batch-photo-upload__badge--passed {
                            background: rgba(34, 197, 94, 0.18);
                            color: rgb(134, 239, 172);
                        }

                        .visora-batch-photo-upload .visora-batch-photo-upload__badge--failed {
                            background: rgba(239, 68, 68, 0.18);
                            color: rgb(252, 165, 165);
                        }

                        .visora-batch-photo-upload .visora-batch-photo-upload__badge--uploading {
                            background: rgba(245, 158, 11, 0.16);
                            color: rgb(252, 211, 77);
                        }
                    </style>
                    <script>
                        (() => {
                            const selector = '.visora-batch-photo-upload';
                            let bootFrame = null;

                            const scheduleBoot = () => {
                                if (bootFrame !== null) {
                                    return;
                                }

                                bootFrame = requestAnimationFrame(() => {
                                    bootFrame = null;
                                    boot();
                                });
                            };

                            const syncUpload = (upload) => {
                                const stats = ensureStats(upload);
                                const queue = ensureQueue(upload);
                                const items = Array.from(upload.querySelectorAll('.filepond--list .filepond--item'));
                                const itemCount = items.length;
                                const passed = items.filter((item) => item.dataset.filepondItemState === 'processing-complete').length;
                                const failed = items.filter((item) => {
                                    const state = item.dataset.filepondItemState || '';

                                    return state.includes('error') || state.includes('invalid');
                                }).length;
                                const uploading = Math.max(itemCount - passed - failed, 0);

                                stats.querySelector('[data-visora-upload-total]').textContent = String(itemCount);
                                stats.querySelector('[data-visora-upload-uploading]').textContent = String(uploading);
                                stats.querySelector('[data-visora-upload-passed]').textContent = String(passed);
                                stats.querySelector('[data-visora-upload-failed]').textContent = String(failed);

                                queue.hidden = itemCount === 0;
                                queue.innerHTML = items.map((item) => {
                                    const name = item.querySelector('.filepond--file-info-main')?.textContent?.trim() || 'Uploading file';
                                    const state = item.dataset.filepondItemState || '';
                                    const label = state === 'processing-complete'
                                        ? 'Passed'
                                        : (state.includes('error') || state.includes('invalid') ? 'Failed' : 'Uploading');
                                    const status = label.toLowerCase();

                                    return `
                                        <div class="visora-batch-photo-upload__row">
                                            <div>
                                                <div class="visora-batch-photo-upload__name">${escapeHtml(name)}</div>
                                            </div>
                                            <span class="visora-batch-photo-upload__badge visora-batch-photo-upload__badge--${status}">${label}</span>
                                        </div>
                                    `;
                                }).join('');
                            };

                            const ensureStats = (upload) => {
                                let stats = upload.querySelector(':scope > .visora-batch-photo-upload__stats');

                                if (stats) {
                                    return stats;
                                }

                                stats = document.createElement('div');
                                stats.className = 'visora-batch-photo-upload__stats';
                                stats.innerHTML = `
                                    <span class="visora-batch-photo-upload__stat">Total: <strong data-visora-upload-total>0</strong></span>
                                    <span class="visora-batch-photo-upload__stat">Uploading: <strong data-visora-upload-uploading>0</strong></span>
                                    <span class="visora-batch-photo-upload__stat">Passed: <strong data-visora-upload-passed>0</strong></span>
                                    <span class="visora-batch-photo-upload__stat">Failed: <strong data-visora-upload-failed>0</strong></span>
                                `;

                                upload.prepend(stats);

                                return stats;
                            };

                            const ensureQueue = (upload) => {
                                let queue = upload.querySelector(':scope > .visora-batch-photo-upload__queue');

                                if (queue) {
                                    return queue;
                                }

                                queue = document.createElement('div');
                                queue.className = 'visora-batch-photo-upload__queue';
                                queue.hidden = true;
                                upload.append(queue);

                                return queue;
                            };

                            const escapeHtml = (value) => value
                                .replaceAll('&', '&amp;')
                                .replaceAll('<', '&lt;')
                                .replaceAll('>', '&gt;')
                                .replaceAll('"', '&quot;')
                                .replaceAll("'", '&#039;');

                            const bindUpload = (upload) => {
                                if (!upload.querySelector('.filepond--root')) {
                                    return;
                                }

                                if (upload.dataset.visoraBatchPhotoUploadBound === 'true') {
                                    syncUpload(upload);

                                    return;
                                }

                                upload.dataset.visoraBatchPhotoUploadBound = 'true';

                                syncUpload(upload);
                            };

                            const boot = () => {
                                document.querySelectorAll(selector).forEach(bindUpload);
                            };

                            const syncFromEvent = (event) => {
                                const upload = event.target?.closest?.(selector);

                                if (! upload) {
                                    return;
                                }

                                requestAnimationFrame(() => syncUpload(upload));
                            };

                            [
                                'FilePond:addfile',
                                'FilePond:removefile',
                                'FilePond:processfile',
                                'FilePond:processfileabort',
                                'FilePond:processfilerevert',
                                'FilePond:processfileprogress',
                                'FilePond:error',
                            ].forEach((eventName) => {
                                document.addEventListener(eventName, syncFromEvent, true);
                            });

                            document.addEventListener('DOMContentLoaded', boot);
                            document.addEventListener('livewire:navigated', scheduleBoot);
                            document.addEventListener('ax-modal-opened', () => {
                                scheduleBoot();
                                setTimeout(scheduleBoot, 100);
                                setTimeout(scheduleBoot, 500);
                            });
                        })();
                    </script>
                HTML)
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                VisoraOperationsOverview::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
