<?php

use App\Http\Controllers\CustomerEventController;
use App\Http\Controllers\EventPhotoController;
use App\Http\Controllers\EventSearchController;
use App\Http\Controllers\LocalCheckoutController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Lunar\Admin\Models\Staff;

if (app()->isLocal()) {
    Route::get('/dev/lunar-login', function () {
        $staff = Staff::where('email', 'admin@visora.local')->firstOrFail();

        Auth::guard('staff')->login($staff);
        request()->session()->regenerate();

        return redirect('/lunar');
    })->name('dev.lunar-login');
}

Route::get('/', [CustomerEventController::class, 'index'])->name('events.index');
Route::get('/events/{event:slug}', [CustomerEventController::class, 'show'])->name('events.show');
Route::post('/events/{event:slug}/search', [EventSearchController::class, 'store'])->name('events.search');
Route::post('/events/{event:slug}/checkout/local', [LocalCheckoutController::class, 'store'])->name('checkout.local');
Route::get('/events/{event:slug}/photos/{photo}/preview', [EventPhotoController::class, 'preview'])->name('photos.preview');
Route::get('/events/{event:slug}/photos/{photo}/download/{orderReference}', [EventPhotoController::class, 'download'])->name('photos.download');
