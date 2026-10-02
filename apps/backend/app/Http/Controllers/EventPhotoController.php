<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventPhotoController extends Controller
{
    public function preview(Event $event, EventPhoto $photo, \Illuminate\Http\Request $request): Response|StreamedResponse
    {
        abort_unless($event->is_active, 404);
        abort_unless($event->isAccessibleBy($request->user()), 403);
        $this->authorizePhoto($event, $photo);

        $path = $photo->storage_path;
        $variant = $request->query('variant');

        if (is_string($variant) && in_array($variant, ['medium', 'thumbnail', 'social_og'], true)) {
            $path = $photo->variantPath($variant) ?: $path;
        }

        return Storage::disk($photo->storage_disk)->response(
            $path,
            $photo->original_filename,
        );
    }

    public function download(Event $event, EventPhoto $photo, string $orderReference): Response|StreamedResponse
    {
        $user = request()->user();
        $variant = request()->query('variant', 'original');

        abort_unless($event->is_active, 404);
        abort_unless($event->isAccessibleBy($user), 403);
        $this->authorizePhoto($event, $photo);

        $unlock = $photo->downloadUnlocks()
            ->where('order_reference', $orderReference)
            ->where('status', 'unlocked')
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere(function ($query) use ($user): void {
                        $query->whereNull('user_id')
                            ->where('customer_email', $user->email);
                    });
            })
            ->where('download_variant', $variant)
            ->firstOrFail();

        if ($unlock->expires_at !== null && $unlock->expires_at->isPast()) {
            abort(403);
        }

        $path = $variant === 'original'
            ? $photo->storage_path
            : ($photo->variantPath((string) $variant) ?: $photo->storage_path);

        return Storage::disk($photo->storage_disk)->download(
            $path,
            $photo->original_filename ?: basename($path),
        );
    }

    private function authorizePhoto(Event $event, EventPhoto $photo): void
    {
        abort_unless($photo->event_id === $event->id, 404);
        abort_unless(Storage::disk($photo->storage_disk)->exists($photo->storage_path), 404);
    }
}
