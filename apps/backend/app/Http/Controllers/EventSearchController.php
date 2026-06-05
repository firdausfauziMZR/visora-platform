<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Services\PgvectorFaceSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EventSearchController extends Controller
{
    public function store(Request $request, Event $event, PgvectorFaceSearch $faceSearch): View|RedirectResponse
    {
        abort_unless($event->is_active, 404);

        $validated = $request->validate([
            'selfie' => ['required', 'image', 'max:10240'],
        ]);

        $selfie = $validated['selfie'];
        $path = $selfie->store('selfie-searches', 'local');
        $absolutePath = Storage::disk('local')->path($path);

        try {
            $result = $faceSearch->searchSelfie(
                $event->id,
                $absolutePath,
                $selfie->getClientOriginalName(),
            );
        } finally {
            Storage::disk('local')->delete($path);
        }

        $photoIds = collect($result['matches'] ?? [])
            ->pluck('photo_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $photos = EventPhoto::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $photoIds)
            ->get()
            ->keyBy('id');

        $matches = collect($result['matches'] ?? [])
            ->map(fn (array $match): array => [
                'photo' => $photos->get((int) $match['photo_id']),
                'cosine_distance' => $match['cosine_distance'],
            ])
            ->filter(fn (array $match): bool => $match['photo'] !== null)
            ->values();

        return view('events.results', [
            'event' => $event,
            'facesDetected' => $result['faces_detected'] ?? 0,
            'matches' => $matches,
            'searchReference' => Str::uuid()->toString(),
        ]);
    }
}
