<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LunarCustomerSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    public function login(): View
    {
        return view('auth.login');
    }

    public function authenticate(Request $request, LunarCustomerSync $customers): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = $request->user();

        if ($user && ! $user->is_admin) {
            $customers->sync($user);
        }

        return redirect()->intended(route('customer.dashboard'));
    }

    public function register(): View
    {
        return view('auth.register');
    }

    public function store(Request $request, LunarCustomerSync $customers): RedirectResponse
    {
        $validated = $request->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::defaults()],
                'image_use_consent' => ['accepted'],
            ],
            [],
            ['image_use_consent' => 'image use permission'],
        );

        unset($validated['image_use_consent']);

        $user = User::create([
            ...$validated,
            'image_use_consent_at' => now(),
            'image_use_consent_version' => '2026-06-10',
        ]);
        $customers->sync($user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('customer.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
