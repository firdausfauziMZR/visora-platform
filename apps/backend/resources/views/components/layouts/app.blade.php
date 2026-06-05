<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'Visora') }}</title>
    <style>
        :root { color-scheme: light; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f5f1; color: #171816; }
        a { color: inherit; }
        .shell { max-width: 1280px; margin: 0 auto; padding: 24px 20px 56px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 28px; }
        .brand { font-weight: 800; font-size: 20px; text-decoration: none; letter-spacing: 0; }
        .nav { display: flex; gap: 10px; align-items: center; }
        .nav a { font-size: 14px; text-decoration: none; padding: 8px 12px; border: 1px solid #d6d8d0; background: #fff; border-radius: 8px; }
        h1 { font-size: clamp(40px, 7vw, 88px); line-height: .92; margin: 0 0 14px; letter-spacing: 0; }
        h2 { font-size: 22px; margin: 0 0 12px; }
        p { color: #5d5d56; line-height: 1.6; }
        .panel { background: #fff; border: 1px solid #deded8; border-radius: 8px; padding: 20px; }
        .event-shell { display: grid; gap: 18px; }
        .event-hero { display: grid; grid-template-columns: minmax(0, 1fr) minmax(280px, 380px); gap: 24px; align-items: end; background: #fff; border: 1px solid #dcddd6; border-radius: 8px; padding: clamp(22px, 4vw, 42px); }
        .event-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
        .pill { display: inline-flex; align-items: center; min-height: 32px; padding: 6px 10px; border: 1px solid #d9dbd3; border-radius: 999px; background: #f8f8f5; color: #4f514b; font-size: 13px; font-weight: 650; }
        .search-card { background: #1d1f1b; color: #fff; border-radius: 8px; padding: 18px; display: grid; gap: 14px; }
        .search-card p { color: #c8cbc2; margin: 0; }
        .tabs { background: #fff; border: 1px solid #dcddd6; border-radius: 8px; overflow: hidden; }
        .tab-controls { display: flex; gap: 0; border-bottom: 1px solid #dcddd6; background: #f8f8f5; overflow-x: auto; }
        .tab-button { min-height: 48px; padding: 0 18px; display: inline-flex; align-items: center; border: 0; border-right: 1px solid #dcddd6; background: transparent; color: #555852; font-weight: 750; cursor: pointer; text-decoration: none; white-space: nowrap; }
        .tab-button[aria-selected="true"] { background: #fff; color: #171816; box-shadow: inset 0 -2px 0 #171816; }
        .tab-panel { padding: 20px; }
        .gallery-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
        .gallery-card { position: relative; display: block; overflow: hidden; border-radius: 8px; border: 1px solid #dcddd6; background: #ebece7; aspect-ratio: 4 / 3; }
        .gallery-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .gallery-card-info { position: absolute; left: 0; right: 0; bottom: 0; padding: 28px 10px 9px; background: linear-gradient(to top, rgba(0,0,0,.72), rgba(0,0,0,0)); color: #fff; display: flex; justify-content: space-between; align-items: end; gap: 8px; }
        .gallery-card-name { font-size: 12px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .badge { display: inline-flex; align-items: center; border-radius: 999px; padding: 4px 7px; background: rgba(255,255,255,.88); color: #252620; font-size: 11px; font-weight: 750; }
        .detail-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .metric { border: 1px solid #dcddd6; border-radius: 8px; padding: 16px; background: #fafaf8; }
        .metric-value { display: block; font-size: 28px; font-weight: 850; line-height: 1; }
        .metric-label { display: block; margin-top: 8px; color: #62645e; font-size: 13px; font-weight: 650; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
        .event-row { display: grid; grid-template-columns: 1fr auto; gap: 16px; align-items: center; }
        .muted { color: #707067; font-size: 14px; }
        .button { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; padding: 10px 14px; border: 1px solid #1e1e1a; background: #1e1e1a; color: #fff; text-decoration: none; font-weight: 650; cursor: pointer; border-radius: 6px; }
        .button.secondary { background: #fff; color: #1e1e1a; border-color: #d8d8d2; }
        .field { display: grid; gap: 8px; margin: 14px 0; }
        label { font-size: 14px; font-weight: 650; }
        input[type="file"], input[type="email"] { width: 100%; border: 1px solid #d8d8d2; background: #fff; border-radius: 6px; padding: 10px; min-height: 42px; color: #171816; }
        .search-card input[type="file"] { border-color: #3f423a; background: #fff; }
        .errors { border: 1px solid #e4b4ad; background: #fff0ee; color: #842114; padding: 12px; border-radius: 6px; margin-bottom: 18px; }
        .photo-card { overflow: hidden; background: #fff; border: 1px solid #deded8; border-radius: 8px; }
        .photo-card img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; background: #ededed; }
        .photo-card-body { padding: 12px; display: grid; gap: 8px; }
        .score { font-variant-numeric: tabular-nums; color: #55554f; font-size: 13px; }
        .empty { border: 1px dashed #c9c9c2; border-radius: 8px; padding: 28px; text-align: center; background: #fff; }
        .empty.compact { min-height: 180px; display: grid; place-items: center; }
        @media (max-width: 640px) {
            .topbar, .event-row { align-items: stretch; flex-direction: column; display: flex; }
            .nav { flex-wrap: wrap; }
            .button { width: 100%; }
            .event-hero { grid-template-columns: 1fr; }
            .gallery-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .detail-grid { grid-template-columns: 1fr; }
            .tab-panel { padding: 14px; }
        }
        @media (min-width: 641px) and (max-width: 980px) {
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
                <a href="{{ route('events.index') }}">Events</a>
                <a href="/admin">Admin</a>
                <a href="/lunar">Lunar</a>
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
</body>
</html>
