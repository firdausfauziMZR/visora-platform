<x-layouts.app title="Create Customer Account">
    <section class="auth-shell">
        <form class="auth-card" method="POST" action="{{ route('register.store') }}">
            @csrf

            <div>
                <span class="eyebrow">Customer Access</span>
                <h1 style="font-size: 42px;">Create account</h1>
                <p>After you create an account, an admin can approve which event galleries you can access.</p>
            </div>

            <div class="field">
                <label for="name">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus>
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required>
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required>
            </div>

            <label class="consent-field">
                <input name="image_use_consent" type="checkbox" value="1" required @checked(old('image_use_consent'))>
                <span>
                    <strong>Image use permission</strong>
                    <small>I agree that Visora may process selfies and event photos I upload or access to find my matches, protect private galleries, and improve photo matching quality. Visora will not sell my images.</small>
                </span>
            </label>

            <button class="button" type="submit">Create account</button>
            <a class="button secondary" href="{{ route('login') }}">Log in instead</a>
        </form>
    </section>
</x-layouts.app>
