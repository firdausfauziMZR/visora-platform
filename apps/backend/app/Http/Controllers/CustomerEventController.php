<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerEventController extends Controller
{
    public function index(): View
    {
        return view('events.index', [
            'events' => Event::query()
                ->where('is_active', true)
                ->withCount(['photos', 'faceEmbeddings'])
                ->orderByDesc('starts_at')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(Request $request, Event $event): View
    {
        abort_unless($event->is_active, 404);
        abort_unless($event->isAccessibleBy($request->user()), 403);

        return view('events.show', [
            'event' => $event->loadCount(['photos', 'faceEmbeddings']),
            'galleryPhotos' => $event->photos()
                ->latest()
                ->limit(15)
                ->get(),
        ]);
    }
}
