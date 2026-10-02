<x-layouts.app title="Customer Login">
    <section class="auth-shell">
        <form class="auth-card" method="POST" action="{{ route('login.store') }}">
            @csrf

            <div>
                <span class="eyebrow">Customer Access</span>
                <h1 style="font-size: 42px;">Log in</h1>
                <p>Use your customer account to open approved event galleries and downloads.</p>
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required>
            </div>

            <label class="inline-field">
                <input name="remember" type="checkbox" value="1">
                Remember me
            </label>

            <button class="button" type="submit">Log in</button>
            <a class="button secondary" href="{{ route('register') }}">Register</a>
        </form>
    </section>
</x-layouts.app>
