<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventPhoto;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventPhotoController extends Controller
{
    public function preview(Event $event, EventPhoto $photo): Response|StreamedResponse
    {
        $this->authorizePhoto($event, $photo);

        return Storage::disk($photo->storage_disk)->response(
            $photo->storage_path,
            $photo->original_filename,
        );
    }

    public function download(Event $event, EventPhoto $photo, string $orderReference): Response|StreamedResponse
    {
        $this->authorizePhoto($event, $photo);

        $unlock = $photo->downloadUnlocks()
            ->where('order_reference', $orderReference)
            ->where('status', 'unlocked')
            ->firstOrFail();

        if ($unlock->expires_at !== null && $unlock->expires_at->isPast()) {
            abort(403);
        }

        return Storage::disk($photo->storage_disk)->download(
            $photo->storage_path,
            $photo->original_filename ?: basename($photo->storage_path),
        );
    }

    private function authorizePhoto(Event $event, EventPhoto $photo): void
    {
        abort_unless($photo->event_id === $event->id, 404);
        abort_unless(Storage::disk($photo->storage_disk)->exists($photo->storage_path), 404);
    }
}
