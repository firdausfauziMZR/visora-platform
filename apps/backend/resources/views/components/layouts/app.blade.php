<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'Visora') }}</title>
    <script>
        (() => {
            const storedTheme = localStorage.getItem('visora-theme');
            const preferredTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.dataset.theme = storedTheme || preferredTheme;
        })();
    </script>
    <style>
        :root {
            color-scheme: light;
            --page-bg: #f4f5f1;
            --surface: #fff;
            --surface-soft: #fafaf8;
            --surface-muted: #f8f8f5;
            --text: #171816;
            --text-muted: #5d5d56;
            --text-soft: #62645e;
            --border: #dcddd6;
            --border-strong: #d6d8d0;
            --button-bg: #1e1e1a;
            --button-text: #fff;
            --button-secondary-bg: #fff;
            --button-secondary-text: #1e1e1a;
            --hero-line: #1d1f1b;
            --success-bg: #e9f4eb;
            --success-text: #245231;
            --warning-bg: #f5eee1;
            --warning-text: #6f4a17;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        html[data-theme="dark"] {
            color-scheme: dark;
            --page-bg: #11120f;
            --surface: #1b1c18;
            --surface-soft: #20221d;
            --surface-muted: #242620;
            --text: #f2f3ee;
            --text-muted: #b8bbb0;
            --text-soft: #a2a69a;
            --border: #363932;
            --border-strong: #474b42;
            --button-bg: #f2f3ee;
            --button-text: #171816;
            --button-secondary-bg: #20221d;
            --button-secondary-text: #f2f3ee;
            --hero-line: #e8eadf;
            --success-bg: #17331f;
            --success-text: #96dfa8;
            --warning-bg: #3a2b12;
            --warning-text: #f1c879;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--page-bg); color: var(--text); }
        a { color: inherit; }
        .shell { max-width: 1280px; margin: 0 auto; padding: 24px 20px 56px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 28px; }
        .brand { font-weight: 800; font-size: 20px; text-decoration: none; letter-spacing: 0; }
        .nav { display: flex; gap: 10px; align-items: center; position: relative; }
        .nav a, .nav button { font-size: 14px; text-decoration: none; padding: 8px 12px; border: 1px solid var(--border-strong); background: var(--surface); border-radius: 8px; color: var(--text); cursor: pointer; }
        .nav form { margin: 0; }
        .account-menu { position: relative; }
        .account-trigger {
            width: 46px;
            min-width: 46px;
            height: 46px;
            padding: 0 !important;
            display: inline-grid;
            place-items: center;
            border-radius: 10px !important;
            background: var(--surface) !important;
            color: var(--text) !important;
            font-size: 18px !important;
            font-weight: 800;
            text-transform: uppercase;
        }
        .account-trigger[aria-expanded="true"] { outline: 2px solid #93c5fd; outline-offset: 2px; }
        .account-panel {
            position: absolute;
            top: calc(100% + 12px);
            right: 0;
            z-index: 20;
            width: min(320px, calc(100vw - 40px));
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: var(--surface);
            box-shadow: 0 18px 48px rgba(0, 0, 0, .22);
        }
        .account-panel[hidden] { display: none; }
        .account-header { display: flex; align-items: center; gap: 12px; padding: 16px 18px; border-bottom: 1px solid var(--border); }
        .account-avatar { width: 28px; height: 28px; display: inline-grid; place-items: center; border-radius: 999px; background: var(--surface-muted); color: var(--text-muted); font-weight: 800; }
        .account-name { font-size: 16px; font-weight: 750; line-height: 1.2; }
        .account-email { margin-top: 3px; color: var(--text-muted); font-size: 13px; word-break: break-all; }
        .account-theme { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; padding: 10px; border-bottom: 1px solid var(--border); }
        .account-theme button {
            min-height: 46px;
            border-radius: 10px;
            font-size: 18px;
        }
        .account-theme button[aria-pressed="true"] {
            border-color: var(--button-bg);
            background: var(--surface-muted);
            color: var(--text);
        }
        .account-items { display: grid; padding: 8px; }
        .account-items form { margin: 0; }
        .account-items a,
        .account-items button {
            width: 100%;
            min-height: 44px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 0;
            background: transparent;
            color: var(--text);
            padding: 9px 10px;
            border-radius: 8px;
            text-align: left;
            font-size: 15px;
        }
        .account-items a:hover,
        .account-items button:hover { background: var(--surface-muted); }
        .account-icon { width: 22px; color: var(--text-muted); text-align: center; }
        .theme-toggle {
            width: 42px;
            min-width: 42px;
            height: 42px;
            padding: 0;
            display: inline-grid;
            place-items: center;
            border-radius: 8px;
            font-size: 18px;
            line-height: 1;
        }
        .theme-toggle:hover { border-color: var(--text-soft); }
        .theme-toggle-icon { display: none; }
        html[data-theme="light"] .theme-toggle-icon.dark { display: block; }
        html[data-theme="dark"] .theme-toggle-icon.light { display: block; }
        h1 { font-size: clamp(40px, 7vw, 88px); line-height: .92; margin: 0 0 14px; letter-spacing: 0; }
        h2 { font-size: 22px; margin: 0 0 12px; }
        p { color: var(--text-muted); line-height: 1.6; }
        .panel { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 20px; }
        .page-hero { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 24px; align-items: end; margin-bottom: 24px; padding: clamp(22px, 4vw, 38px); background: var(--surface); border: 1px solid var(--border); border-radius: 8px; }
        .page-hero p { max-width: 680px; margin: 0; font-size: 17px; }
        .eyebrow { display: inline-flex; align-items: center; margin-bottom: 14px; color: var(--text-soft); font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .hero-summary { display: grid; grid-template-columns: repeat(3, minmax(96px, 1fr)); gap: 10px; min-width: min(420px, 100%); }
        .summary-tile { border: 1px solid var(--border); border-radius: 8px; padding: 14px; background: var(--surface-soft); }
        .summary-value { display: block; color: var(--text); font-size: 26px; font-weight: 850; line-height: 1; }
        .summary-label { display: block; margin-top: 7px; color: var(--text-soft); font-size: 12px; font-weight: 700; }
        .event-shell { display: grid; gap: 18px; }
        .event-hero { display: grid; grid-template-columns: minmax(0, 1fr) minmax(280px, 380px); gap: 24px; align-items: end; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: clamp(22px, 4vw, 42px); }
        .event-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
        .pill { display: inline-flex; align-items: center; min-height: 32px; padding: 6px 10px; border: 1px solid var(--border-strong); border-radius: 999px; background: var(--surface-muted); color: var(--text-muted); font-size: 13px; font-weight: 650; }
        .search-card { background: #1d1f1b; color: #fff; border-radius: 8px; padding: 18px; display: grid; gap: 14px; }
        .search-card p { color: #c8cbc2; margin: 0; }
        .tabs { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
        .tab-controls { display: flex; gap: 0; border-bottom: 1px solid var(--border); background: var(--surface-muted); overflow-x: auto; }
        .tab-button { min-height: 48px; padding: 0 18px; display: inline-flex; align-items: center; border: 0; border-right: 1px solid var(--border); background: transparent; color: var(--text-muted); font-weight: 750; cursor: pointer; text-decoration: none; white-space: nowrap; }
        .tab-button[aria-selected="true"] { background: var(--surface); color: var(--text); box-shadow: inset 0 -2px 0 var(--text); }
        .tab-panel { padding: 20px; }
        .gallery-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
        .gallery-card { position: relative; display: block; overflow: hidden; border-radius: 8px; border: 1px solid var(--border); background: var(--surface-muted); aspect-ratio: 4 / 3; }
        .gallery-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .gallery-card-info { position: absolute; left: 0; right: 0; bottom: 0; padding: 28px 10px 9px; background: linear-gradient(to top, rgba(0,0,0,.72), rgba(0,0,0,0)); color: #fff; display: flex; justify-content: space-between; align-items: end; gap: 8px; }
        .gallery-card-name { font-size: 12px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .badge { display: inline-flex; align-items: center; border-radius: 999px; padding: 4px 7px; background: rgba(255,255,255,.88); color: #252620; font-size: 11px; font-weight: 750; }
        .detail-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .metric { border: 1px solid var(--border); border-radius: 8px; padding: 16px; background: var(--surface-soft); }
        .metric-value { display: block; font-size: 28px; font-weight: 850; line-height: 1; }
        .metric-label { display: block; margin-top: 8px; color: var(--text-soft); font-size: 13px; font-weight: 650; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
        .events-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(270px, 1fr)); gap: 16px; }
        .event-card { position: relative; display: grid; min-height: 210px; padding: 18px; overflow: hidden; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; text-decoration: none; transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease; }
        .event-card::before { content: ""; position: absolute; inset: 0 0 auto; height: 4px; background: var(--hero-line); }
        .event-card:hover { transform: translateY(-2px); border-color: var(--border-strong); box-shadow: 0 14px 34px rgba(0, 0, 0, .16); }
        .event-card-main { display: grid; gap: 14px; align-content: start; }
        .event-card h2 { max-width: 13ch; margin: 0; font-size: clamp(23px, 2.2vw, 30px); line-height: 1.02; }
        .event-card .muted { max-width: 32ch; }
        .event-card-footer { display: flex; align-items: end; justify-content: space-between; gap: 14px; margin-top: 28px; }
        .event-card-stats { display: flex; flex-wrap: wrap; gap: 7px; }
        .event-stat { display: inline-flex; min-height: 28px; align-items: center; padding: 5px 8px; border-radius: 999px; background: var(--surface-muted); color: var(--text-muted); font-size: 12px; font-weight: 750; }
        .event-stat.ready { background: var(--success-bg); color: var(--success-text); }
        .event-stat.pending { background: var(--warning-bg); color: var(--warning-text); }
        .event-open { display: inline-flex; min-width: 42px; min-height: 42px; align-items: center; justify-content: center; border: 1px solid var(--border-strong); border-radius: 999px; background: var(--surface); color: var(--text); font-size: 22px; line-height: 1; }
        .event-row { display: grid; grid-template-columns: 1fr auto; gap: 16px; align-items: center; }
        .section-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .section-heading h2 { margin: 0; }
        .muted { color: var(--text-muted); font-size: 14px; }
        .button { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; padding: 10px 14px; border: 1px solid var(--button-bg); background: var(--button-bg); color: var(--button-text); text-decoration: none; font: inherit; font-size: 16px; line-height: 1.2; font-weight: 650; cursor: pointer; border-radius: 6px; }
        .button.secondary { background: var(--button-secondary-bg); color: var(--button-secondary-text); border-color: var(--border-strong); }
        .field { display: grid; gap: 8px; margin: 14px 0; }
        label { font-size: 14px; font-weight: 650; }
        input[type="file"], input[type="email"], input[type="text"], input[type="password"], select { width: 100%; border: 1px solid var(--border-strong); background: var(--surface); border-radius: 6px; padding: 10px; min-height: 42px; color: var(--text); }
        input[type="file"] { background: #fff; color: #171816; color-scheme: light; }
        input[type="file"]::file-selector-button { min-height: 34px; margin-right: 10px; border: 0; border-radius: 4px; background: #686868; color: #fff; font-weight: 650; padding: 6px 12px; cursor: pointer; }
        input[type="checkbox"] { width: 16px; height: 16px; }
        .search-card input[type="file"] { border-color: #3f423a; background: #fff; }
        .errors { border: 1px solid #e4b4ad; background: #fff0ee; color: #842114; padding: 12px; border-radius: 6px; margin-bottom: 18px; }
        .photo-card { overflow: hidden; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; }
        .photo-results-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 320px)); gap: 16px; align-items: start; margin: 18px 0; }
        .photo-card-preview { display: block; width: 100%; padding: 0; border: 0; background: transparent; cursor: zoom-in; }
        .photo-card img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; background: var(--surface-muted); display: block; }
        .photo-card-body { padding: 12px; display: grid; gap: 8px; }
        .photo-select-row { justify-content: flex-start; align-items: start; color: var(--text); font-weight: 650; }
        .compact-button { min-height: 36px; padding: 8px 10px; font-size: 14px; }
        .score { font-variant-numeric: tabular-nums; color: var(--text-muted); font-size: 13px; }
        .photo-modal { width: min(1080px, calc(100vw - 32px)); max-height: calc(100vh - 32px); padding: 0; overflow: hidden; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--text); }
        .photo-modal::backdrop { background: rgba(0, 0, 0, .72); }
        .photo-modal-layout { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(320px, .65fr); max-height: calc(100vh - 32px); }
        .photo-modal-image { min-height: 420px; background: #0c0d0b; display: grid; place-items: center; }
        .photo-modal-image img { width: 100%; height: 100%; max-height: calc(100vh - 32px); object-fit: contain; display: block; }
        .photo-modal-panel { display: grid; align-content: start; gap: 16px; padding: 20px; overflow-y: auto; }
        .photo-modal-panel h2 { font-size: 26px; line-height: 1.05; margin: 0 0 8px; overflow-wrap: anywhere; }
        .photo-modal-panel p { margin: 0; }
        .package-checkout-panel { display: grid; gap: 16px; }
        .package-checkout-panel p { margin: 0; }
        .package-choice-list { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
        .package-choice { position: relative; display: grid; grid-template-columns: 18px minmax(0, 1fr); gap: 10px; align-items: start; min-height: 104px; padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface-soft); cursor: pointer; }
        .package-choice:has(input:checked) { border-color: var(--button-bg); box-shadow: inset 0 0 0 1px var(--button-bg); }
        .package-choice input { margin-top: 3px; }
        .package-choice strong { display: block; margin-bottom: 5px; font-size: 15px; line-height: 1.25; }
        .package-choice small { display: block; color: var(--text-muted); font-size: 13px; line-height: 1.4; }
        .package-list { display: grid; gap: 8px; }
        .package-row { display: grid; gap: 4px; padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface-soft); }
        .package-row span { color: var(--text-muted); font-size: 13px; line-height: 1.45; }
        .photo-modal-actions { display: flex; justify-content: end; gap: 8px; }
        .empty { border: 1px dashed var(--border-strong); border-radius: 8px; padding: 28px; text-align: center; background: var(--surface); }
        .auth-shell { max-width: 460px; margin: 8vh auto 0; }
        .auth-card { display: grid; gap: 16px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 22px; }
        .inline-field { display: flex; gap: 8px; align-items: center; color: var(--text-muted); font-size: 14px; }
        .consent-field { display: grid; grid-template-columns: 18px minmax(0, 1fr); gap: 10px; align-items: start; color: var(--text); font-size: 14px; line-height: 1.45; }
        .consent-field input { margin-top: 3px; }
        .consent-field strong { display: block; margin-bottom: 3px; }
        .consent-field small { display: block; color: var(--text-muted); font-size: 13px; line-height: 1.45; }
        .download-list { display: grid; gap: 10px; }
        .download-list-scroll { max-height: 340px; overflow-y: auto; padding-right: 4px; }
        .download-row { display: grid; grid-template-columns: 72px minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 10px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; }
        .download-thumb { width: 72px; aspect-ratio: 1; object-fit: cover; border-radius: 6px; background: var(--surface-muted); }
        .empty.compact { min-height: 180px; display: grid; place-items: center; }
        @media (max-width: 640px) {
            .topbar, .event-row { align-items: stretch; flex-direction: column; display: flex; }
            .nav { flex-wrap: wrap; justify-content: flex-end; }
            .account-panel { left: 0; right: auto; width: 100%; }
            .button { width: 100%; }
            .page-hero { grid-template-columns: 1fr; }
            .hero-summary { grid-template-columns: 1fr; }
            .event-hero { grid-template-columns: 1fr; }
            .gallery-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .detail-grid { grid-template-columns: 1fr; }
            .photo-modal-layout { grid-template-columns: 1fr; }
            .photo-modal-image { min-height: 280px; }
            .photo-modal-panel { padding: 16px; }
            .package-checkout-panel { grid-template-columns: 1fr; }
            .package-choice-list { grid-template-columns: 1fr; }
            .photo-results-grid { grid-template-columns: 1fr; }
            .section-heading { align-items: stretch; flex-direction: column; }
            .download-row { grid-template-columns: 56px minmax(0, 1fr); }
            .download-row .button { grid-column: 1 / -1; }
            .download-thumb { width: 56px; }
            .tab-panel { padding: 14px; }
        }
        @media (min-width: 641px) and (max-width: 980px) {
            .page-hero { grid-template-columns: 1fr; }
            .event-hero { grid-template-columns: 1fr; }
            .gallery-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
    </style>
</head>
<body>
    <main class="shell">
        <header class="topbar">
            <a class="brand" href="{{ route('events.index') }}">Visora</a>
            <nav class="nav">
                @auth
                    @php($user = auth()->user())
                    <div class="account-menu">
                        <button class="account-trigger" type="button" data-account-menu-trigger aria-expanded="false" aria-controls="customer-account-menu" aria-label="Open account menu">
                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name ?: $user->email, 0, 1)) }}
                        </button>

                        <div class="account-panel" id="customer-account-menu" data-account-menu hidden>
                            <div class="account-header">
                                <span class="account-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name ?: $user->email, 0, 1)) }}</span>
                                <div>
                                    <div class="account-name">{{ $user->name }}</div>
                                    <div class="account-email">{{ $user->email }}</div>
                                </div>
                            </div>

                            <div class="account-theme" aria-label="Theme">
                                <button type="button" data-theme-choice="light" aria-label="Use light mode">☼</button>
                                <button type="button" data-theme-choice="dark" aria-label="Use dark mode">◐</button>
                            </div>

                            <div class="account-items">
                                @unless (request()->routeIs('customer.dashboard', 'events.index'))
                                    <a href="{{ route('customer.dashboard') }}"><span class="account-icon" aria-hidden="true">⌂</span> My Galleries</a>
                                @endunless
                                @unless (request()->routeIs('customer.downloads'))
                                    <a href="{{ route('customer.downloads') }}"><span class="account-icon" aria-hidden="true">↓</span> My Downloads</a>
                                @endunless
                                @if ($user?->canAccessPanel(filament()->getPanel('admin')))
                                    <a href="/admin"><span class="account-icon" aria-hidden="true">A</span> Admin</a>
                                @endif
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit"><span class="account-icon" aria-hidden="true">↩</span> Sign out</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @else
                    @unless (request()->routeIs('login', 'register'))
                        <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark mode" title="Switch to dark mode">
                            <span class="theme-toggle-icon dark" aria-hidden="true">◐</span>
                            <span class="theme-toggle-icon light" aria-hidden="true">☼</span>
                        </button>
                        <a href="{{ route('login') }}">Log in</a>
                        <a href="{{ route('register') }}">Register</a>
                    @endunless
                @endauth
            </nav>
        </header>

        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        {{ $slot }}
    </main>
    <script>
        (() => {
            const themeToggle = document.querySelector('[data-theme-toggle]');
            const themeChoices = Array.from(document.querySelectorAll('[data-theme-choice]'));
            const accountTrigger = document.querySelector('[data-account-menu-trigger]');
            const accountMenu = document.querySelector('[data-account-menu]');

            const setTheme = (theme) => {
                document.documentElement.dataset.theme = theme;
                localStorage.setItem('visora-theme', theme);

                if (themeToggle) {
                    const label = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
                    themeToggle.setAttribute('aria-label', label);
                    themeToggle.setAttribute('title', label);
                    themeToggle.setAttribute('aria-pressed', String(theme === 'dark'));
                }

                themeChoices.forEach((button) => {
                    button.setAttribute('aria-pressed', String(button.dataset.themeChoice === theme));
                });
            };

            setTheme(document.documentElement.dataset.theme || 'light');
            themeToggle?.addEventListener('click', () => {
                setTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');
            });
            themeChoices.forEach((button) => {
                button.addEventListener('click', () => setTheme(button.dataset.themeChoice || 'light'));
            });

            accountTrigger?.addEventListener('click', () => {
                const isOpen = accountTrigger.getAttribute('aria-expanded') === 'true';
                accountTrigger.setAttribute('aria-expanded', String(!isOpen));

                if (accountMenu) {
                    accountMenu.hidden = isOpen;
                }
            });

            document.addEventListener('click', (event) => {
                if (! accountMenu || ! accountTrigger || accountMenu.hidden) {
                    return;
                }

                if (accountMenu.contains(event.target) || accountTrigger.contains(event.target)) {
                    return;
                }

                accountTrigger.setAttribute('aria-expanded', 'false');
                accountMenu.hidden = true;
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape' || ! accountMenu || ! accountTrigger) {
                    return;
                }

                accountTrigger.setAttribute('aria-expanded', 'false');
                accountMenu.hidden = true;
                accountTrigger.focus();
            });
        })();
    </script>
</body>
</html>
