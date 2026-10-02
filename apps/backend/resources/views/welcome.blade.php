<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Visora</title>
    <style>
        :root {
            color-scheme: light;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            --ink: #11130f;
            --muted: #5d6258;
            --paper: #f4f5f1;
            --card: #ffffff;
            --line: #d9dcd3;
            --accent: #d6f06d;
            --accent-ink: #151710;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--paper); color: var(--ink); }
        a { color: inherit; }
        .site-header {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 22px clamp(18px, 4vw, 52px);
            color: #fff;
        }
        .brand { font-size: 22px; font-weight: 850; text-decoration: none; letter-spacing: 0; }
        .nav { display: flex; align-items: center; gap: 10px; }
        .nav a, .hero-actions a {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            font-weight: 750;
            text-decoration: none;
        }
        .nav a { border: 1px solid rgba(255,255,255,.36); background: rgba(0,0,0,.24); backdrop-filter: blur(12px); }
        .nav a.primary, .hero-actions a.primary { border-color: var(--accent); background: var(--accent); color: var(--accent-ink); }
        .hero {
            position: relative;
            min-height: 86vh;
            display: grid;
            align-items: end;
            padding: 108px clamp(18px, 4vw, 52px) 48px;
            overflow: hidden;
            background: #12140f;
            color: #fff;
        }
        .hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(12,14,10,.86) 0%, rgba(12,14,10,.68) 42%, rgba(12,14,10,.12) 100%),
                linear-gradient(0deg, rgba(12,14,10,.72) 0%, rgba(12,14,10,.08) 42%),
                url("{{ asset('images/visora-hero.png') }}") center / cover no-repeat;
        }
        .hero-content { position: relative; max-width: 760px; }
        .eyebrow {
            display: inline-flex;
            margin-bottom: 18px;
            color: rgba(255,255,255,.78);
            font-size: 12px;
            font-weight: 850;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        h1 {
            margin: 0;
            max-width: 10ch;
            font-size: clamp(58px, 10vw, 128px);
            line-height: .88;
            letter-spacing: 0;
        }
        .hero-copy {
            max-width: 630px;
            margin: 22px 0 0;
            color: rgba(255,255,255,.82);
            font-size: clamp(18px, 2.2vw, 24px);
            line-height: 1.45;
        }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 28px; }
        .hero-actions a.secondary { border: 1px solid rgba(255,255,255,.34); background: rgba(255,255,255,.08); color: #fff; }
        .proof-strip {
            position: relative;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1px;
            margin-top: 38px;
            max-width: 720px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 8px;
            background: rgba(255,255,255,.18);
        }
        .proof-item { padding: 16px; background: rgba(9,10,8,.58); backdrop-filter: blur(12px); }
        .proof-value { display: block; font-size: 26px; font-weight: 850; }
        .proof-label { display: block; margin-top: 5px; color: rgba(255,255,255,.68); font-size: 13px; font-weight: 650; }
        .section { padding: 56px clamp(18px, 4vw, 52px); }
        .section-header { max-width: 760px; margin-bottom: 26px; }
        .section h2 { margin: 0; font-size: clamp(34px, 5vw, 58px); line-height: .98; }
        .section p { color: var(--muted); font-size: 17px; line-height: 1.65; }
        .features { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        .feature {
            min-height: 220px;
            padding: 22px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--card);
        }
        .feature-icon {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            margin-bottom: 34px;
            border-radius: 8px;
            background: #171816;
            color: #fff;
            font-size: 20px;
            font-weight: 850;
        }
        .feature h3 { margin: 0 0 10px; font-size: 22px; }
        .feature p { margin: 0; font-size: 15px; }
        .flow-panel {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--card);
            padding: clamp(22px, 4vw, 38px);
        }
        .steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin: 24px 0 0; padding: 0; list-style: none; counter-reset: step; }
        .steps li {
            display: grid;
            align-content: start;
            gap: 18px;
            min-height: 210px;
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fafbf7;
            counter-increment: step;
        }
        .steps li::before {
            content: counter(step);
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: var(--accent);
            color: var(--accent-ink);
            font-weight: 850;
        }
        .steps strong { display: block; margin-bottom: 8px; font-size: 20px; line-height: 1.15; }
        .steps span { color: var(--muted); line-height: 1.55; }
        @media (max-width: 820px) {
            .site-header { position: absolute; align-items: flex-start; }
            .nav { flex-wrap: wrap; justify-content: flex-end; }
            .hero { min-height: 92vh; }
            .proof-strip, .features, .steps { grid-template-columns: 1fr; }
            .steps li { min-height: auto; }
            h1 { max-width: 8ch; }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="brand" href="{{ route('events.index') }}">Visora</a>
        <nav class="nav" aria-label="Primary">
            <a href="{{ route('login') }}">Log in</a>
            <a class="primary" href="{{ route('register') }}">Register now</a>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="hero-content">
                <span class="eyebrow">Private event photo search</span>
                <h1>Find every photo you are in.</h1>
                <p class="hero-copy">Visora helps event organizers publish private galleries where customers unlock access with a code or QR, search by selfie, and download their matched photos.</p>
                <div class="hero-actions">
                    <a class="primary" href="{{ route('register') }}">Register now</a>
                </div>
                <div class="proof-strip" aria-label="Visora highlights">
                    <div class="proof-item">
                        <span class="proof-value">QR</span>
                        <span class="proof-label">Fast event access</span>
                    </div>
                    <div class="proof-item">
                        <span class="proof-value">Selfie</span>
                        <span class="proof-label">Photo matching</span>
                    </div>
                    <div class="proof-item">
                        <span class="proof-value">Private</span>
                        <span class="proof-label">Approval controls</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-header">
                <h2>Built for events with too many photos to browse manually.</h2>
                <p>Marathons, school events, dance shows, weddings, and corporate days can produce hundreds or thousands of images. Visora turns that archive into a searchable customer experience.</p>
            </div>

            <div class="features">
                <article class="feature">
                    <div class="feature-icon">1</div>
                    <h3>Control gallery access</h3>
                    <p>Use event codes, QR links, manual approval, or pending requests depending on how private the event should be.</p>
                </article>
                <article class="feature">
                    <div class="feature-icon">2</div>
                    <h3>Search by selfie</h3>
                    <p>Customers upload a selfie and Visora returns likely matches from the approved event gallery.</p>
                </article>
                <article class="feature">
                    <div class="feature-icon">3</div>
                    <h3>Unlock downloads</h3>
                    <p>Matched photos can be unlocked and downloaded from a simple customer dashboard.</p>
                </article>
            </div>
        </section>

        <section class="section">
            <div class="flow-panel">
                <div class="section-header" style="margin-bottom: 0;">
                    <h2>How customers use Visora</h2>
                    <p>Access the event gallery your organizer shared, then let Visora narrow the gallery down to photos that are likely yours.</p>
                </div>
                <ol class="steps">
                    <li><div><strong>Register or log in</strong><span>Create your customer account so your approved event galleries and downloads stay in one place.</span></div></li>
                    <li><div><strong>Scan the QR or enter the event code</strong><span>Your event organizer gives you access to the right private gallery.</span></div></li>
                    <li><div><strong>Search and download</strong><span>Upload a selfie, review your matched photos, and download the images you unlock.</span></div></li>
                </ol>
            </div>
        </section>
    </main>
</body>
</html>
