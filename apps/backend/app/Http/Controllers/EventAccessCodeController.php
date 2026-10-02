<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EventAccessCodeController extends Controller
{
    public function show(string $accessCode): RedirectResponse
    {
        return $this->redeem(Event::normalizeAccessCode($accessCode));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'access_code' => ['required', 'string', 'max:64'],
        ]);

        return $this->redeem(Event::normalizeAccessCode($validated['access_code']));
    }

    private function redeem(?string $code): RedirectResponse
    {
        $event = $code
            ? Event::query()
                ->where('access_code', $code)
                ->where('is_active', true)
                ->first()
            : null;

        if (! $event) {
            return back()
                ->withErrors(['access_code' => 'This event code is invalid or the event is not active.'])
                ->withInput();
        }

        if ($event->isAccessibleBy(request()->user())) {
            return redirect()
                ->route('events.show', $event)
                ->with('status', 'You already have access to this event.');
        }

        if ($event->access_mode === Event::ACCESS_MODE_MANUAL) {
            return redirect()
                ->route('customer.dashboard')
                ->withErrors(['access_code' => 'This event requires manual admin approval.']);
        }

        if ($event->access_mode === Event::ACCESS_MODE_CODE_CREATES_REQUEST) {
            $event->pendingAccessRequests()->syncWithoutDetaching([
                request()->user()->id => ['approved_at' => null],
            ]);

            return redirect()
                ->route('customer.dashboard')
                ->with('status', 'Access request sent. An admin can approve it from the event admin page.');
        }

        $event->approvedUsers()->syncWithoutDetaching([
            request()->user()->id => ['approved_at' => now()],
        ]);

        return redirect()
            ->route('events.show', $event)
            ->with('status', 'Event access granted.');
    }
}
