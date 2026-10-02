<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerDownloadController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('customer.downloads', [
            'downloads' => $request->user()
                ->downloadUnlocks()
                ->with(['photo.event'])
                ->where('status', 'unlocked')
                ->latest('unlocked_at')
                ->get(),
        ]);
    }
}
