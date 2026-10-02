<?php

namespace App\Http\Controllers;

use App\Services\LunarCustomerSync;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CustomerDashboardController extends Controller
{
    public function __invoke(Request $request, LunarCustomerSync $customers): View
    {
        $user = $request->user();

        if (! $user->is_admin) {
            $customers->sync($user);
        }

        $events = $user->is_admin
            ? $this->adminEvents()
            : $user->approvedEvents()
                ->where('is_active', true)
                ->withCount(['photos', 'faceEmbeddings'])
                ->orderByDesc('starts_at')
                ->orderBy('name')
                ->get();

        return view('customer.dashboard', [
            'events' => $events,
            'downloads' => $user->downloadUnlocks()
                ->with(['photo.event'])
                ->where('status', 'unlocked')
                ->latest('unlocked_at')
                ->limit(12)
                ->get(),
        ]);
    }

    /**
     * @return Collection<int, \App\Models\Event>
     */
    private function adminEvents(): Collection
    {
        return \App\Models\Event::query()
            ->where('is_active', true)
            ->withCount(['photos', 'faceEmbeddings'])
            ->orderByDesc('starts_at')
            ->orderBy('name')
            ->get();
    }
}
