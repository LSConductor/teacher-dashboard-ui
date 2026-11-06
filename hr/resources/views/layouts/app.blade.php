<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Casual HR' }} · Living School</title>
    <style>
        :root { --ink:#1c2430; --navy:#16324f; --paper:#f4f1ea; --line:#e3dcd0; --green:#1f7a4d; --amber:#8a5a00; --red:#8f2d2d; }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--paper); color:var(--ink); font-family: Georgia, "Iowan Old Style", serif; }
        header { background:#fff; border-bottom:1px solid var(--line); padding:14px 22px; display:flex; justify-content:space-between; gap:16px; align-items:center; }
        header strong { letter-spacing:.04em; }
        nav a, .link { color:var(--navy); font-family: system-ui, sans-serif; font-size:14px; margin-right:14px; text-decoration:none; }
        .wrap { max-width:1200px; margin:0 auto; padding:22px; }
        .card { background:#fff; border:1px solid var(--line); border-radius:12px; padding:16px 18px; margin-bottom:16px; }
        h1 { font-size:32px; margin:0 0 8px; }
        h2 { font-size:20px; margin:0 0 10px; }
        table { width:100%; border-collapse:collapse; background:#fff; }
        th, td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--line); font-family:system-ui, sans-serif; font-size:14px; vertical-align:top; }
        .light { display:inline-block; padding:2px 8px; border-radius:99px; font-size:12px; font-family:system-ui, sans-serif; text-transform:uppercase; letter-spacing:.04em; }
        .green { background:#e5f4ec; color:var(--green); }
        .amber { background:#fff4d6; color:var(--amber); }
        .red { background:#fde8e8; color:var(--red); }
        button, .button { background:var(--navy); color:#fff; border:0; border-radius:6px; padding:8px 14px; font-family:system-ui, sans-serif; text-decoration:none; display:inline-block; }
        input, select, textarea { width:100%; padding:8px; border:1px solid var(--line); border-radius:6px; font-family:system-ui, sans-serif; }
        label { display:block; font-family:system-ui, sans-serif; font-size:13px; margin:12px 0 4px; }
        .error { color:var(--red); font-family:system-ui, sans-serif; font-size:14px; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .muted { color:#5c6570; font-family:system-ui, sans-serif; font-size:14px; }
        @media (max-width:720px) { .grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<header>
    <div>
        <strong>Living School</strong>
        <span class="muted">Casual HR</span>
    </div>
    @auth
        <nav>
            <a href="{{ route('casuals.index') }}">Casuals</a>
            @if(in_array(auth()->user()->role, ['hr','conductor']))
                <a href="{{ route('templates.index') }}">Templates</a>
                <a href="{{ route('templates.complete') }}">Complete a contract</a>
                <a href="{{ route('audit.index') }}">Audit</a>
                <a href="{{ route('roadmap') }}">Later</a>
            @endif
            <form action="{{ route('logout') }}" method="post" style="display:inline">@csrf<button type="submit">Sign out</button></form>
        </nav>
    @endauth
</header>
<main class="wrap">
    @if($errors->any())
        <div class="card error">{{ $errors->first() }}</div>
    @endif
    @if(session('invite_url'))
        <div class="card">Invite link, shown once: <a href="{{ session('invite_url') }}">{{ session('invite_url') }}</a></div>
    @endif
    @yield('content')
</main>
</body>
</html>
