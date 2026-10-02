<?php

use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\CustomerDashboardController;
use App\Http\Controllers\CustomerDownloadController;
use App\Http\Controllers\CustomerEventController;
use App\Http\Controllers\EventAccessCodeController;
use App\Http\Controllers\EventAccessQrCodeController;
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

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('customer.dashboard');
    }

    return view('welcome');
})->name('events.index');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [CustomerAuthController::class, 'login'])->name('login');
    Route::post('/login', [CustomerAuthController::class, 'authenticate'])->name('login.store');
    Route::get('/register', [CustomerAuthController::class, 'register'])->name('register');
    Route::post('/register', [CustomerAuthController::class, 'store'])->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', CustomerDashboardController::class)->name('customer.dashboard');
    Route::get('/downloads', CustomerDownloadController::class)->name('customer.downloads');
    Route::get('/event-access-codes/{accessCode}', [EventAccessCodeController::class, 'show'])->name('event-access-codes.show');
    Route::post('/event-access-codes', [EventAccessCodeController::class, 'store'])->name('event-access-codes.store');
    Route::get('/admin/events/{event}/access-qr.svg', EventAccessQrCodeController::class)->name('events.access-qr');
    Route::get('/events/{event:slug}', [CustomerEventController::class, 'show'])->name('events.show');
    Route::post('/events/{event:slug}/search', [EventSearchController::class, 'store'])->name('events.search');
    Route::post('/events/{event:slug}/checkout/local', [LocalCheckoutController::class, 'store'])->name('checkout.local');
    Route::get('/events/{event:slug}/checkout/{orderReference}/pay', [LocalCheckoutController::class, 'pay'])->name('checkout.local.pay');
    Route::post('/events/{event:slug}/checkout/{orderReference}/pay', [LocalCheckoutController::class, 'confirmLocalPayment'])->name('checkout.local.confirm');
    Route::get('/events/{event:slug}/checkout/{orderReference}/complete', [LocalCheckoutController::class, 'complete'])->name('checkout.local.complete');
    Route::get('/events/{event:slug}/photos/{photo}/preview', [EventPhotoController::class, 'preview'])->name('photos.preview');
    Route::get('/events/{event:slug}/photos/{photo}/download/{orderReference}', [EventPhotoController::class, 'download'])->name('photos.download');
});
