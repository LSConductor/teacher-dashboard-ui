<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Casual HR' }} · Living School</title>
    <style>
        :root {
            --ink: #1a2330;
            --navy: #14324a;
            --paper: #efece4;
            --card: #fff;
            --line: #ddd4c6;
            --muted: #5c6570;
            --green: #14663d;
            --green-bg: #e5f4ec;
            --amber: #8a4b00;
            --amber-bg: #fff1d6;
            --red: #8d2430;
            --red-bg: #fde8e8;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--paper);
            color: var(--ink);
            font-family: "Segoe UI", system-ui, sans-serif;
            font-size: 16px;
            line-height: 1.45;
        }
        header {
            background: var(--navy);
            color: #fff;
            padding: 16px 28px;
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: center;
        }
        .brand { line-height: 1.2; }
        .brand span { display: block; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; opacity: .75; }
        .brand strong { font-size: 20px; font-weight: 700; }
        nav { display: flex; flex-wrap: wrap; gap: 8px 18px; align-items: center; }
        nav a { color: #fff; font-weight: 650; text-decoration: none; font-size: 15px; }
        nav a:hover { text-decoration: underline; }
        .who { color: #d5e2ec; font-size: 14px; }
        button, .button {
            background: var(--navy);
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: 10px 14px;
            min-height: 44px;
            font: 650 15px/1 "Segoe UI", system-ui, sans-serif;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        button.quiet, a.quiet {
            background: transparent;
            color: #fff;
            border: 1px solid rgba(255,255,255,.45);
        }
        a.ghost, button.ghost {
            background: #fff;
            color: var(--navy);
            border: 1px solid var(--navy);
        }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 28px 22px 48px; }
        h1 { font-size: 34px; line-height: 1.15; margin: 0 0 8px; font-weight: 720; }
        h2 { font-size: 20px; margin: 0 0 8px; }
        .lede { color: var(--muted); margin: 0 0 18px; max-width: 68ch; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 18px 20px; margin-bottom: 14px; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: end; background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 14px 16px; margin-bottom: 18px; }
        .toolbar label { display: block; font-size: 13px; font-weight: 650; margin: 0 0 4px; }
        .toolbar input, .toolbar select { min-width: 180px; }
        input, select, textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--line); border-radius: 8px; font: 16px/1.3 "Segoe UI", system-ui, sans-serif; background: #fff; }
        label { display: block; font-size: 14px; font-weight: 650; margin: 14px 0 4px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { font-size: 13px; letter-spacing: .04em; text-transform: uppercase; color: var(--muted); }
        .status { display: inline-block; font-weight: 750; font-size: 15px; padding: 8px 10px; border-radius: 8px; }
        .green { background: var(--green-bg); color: var(--green); }
        .amber { background: var(--amber-bg); color: var(--amber); }
        .red { background: var(--red-bg); color: var(--red); }
        .person { display: grid; grid-template-columns: 168px 1fr; gap: 8px 20px; background: #fff; border: 1px solid var(--line); border-left: 8px solid var(--line); border-radius: 14px; padding: 18px 18px 16px; margin: 0 0 14px; }
        .person.green { border-left-color: var(--green); }
        .person.amber { border-left-color: var(--amber); }
        .person.red { border-left-color: var(--red); }
        .portrait { width: 144px; height: 144px; object-fit: cover; border-radius: 12px; display: block; margin-bottom: 10px; background: #f4f1ea; }
        article[draggable="true"] { cursor: grab; }
        article[draggable="true"].dragging { opacity: .55; }
        .person h2 { font-size: 24px; margin: 0; }
        .person h2 a { color: inherit; text-decoration: none; }
        .person h2 a:hover { text-decoration: underline; }
        .meta { color: var(--muted); margin: 4px 0 0; }
        .actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
        .error { color: var(--red); }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .muted { color: var(--muted); }
        a.link { color: var(--navy); }
        @media (max-width: 720px) {
            header { align-items: flex-start; flex-direction: column; }
            .person, .grid { grid-template-columns: 1fr; }
            .wrap { padding: 18px 14px 36px; }
            h1 { font-size: 28px; }
        }
    </style>
</head>
<body>
<header>
    <div class="brand">
        <span>Living School</span>
        <strong>Casual teachers</strong>
    </div>
    @auth
        <nav>
            @if(\App\Hr\Roles::isCasual(auth()->user()))
                <a href="{{ route('start') }}">Start</a>
            @endif
            <a href="{{ route('people') }}">People</a>
            <a href="{{ route('casuals.index') }}">List</a>
            @if(\App\Hr\Roles::manages(auth()->user()))
                <a href="{{ route('setup') }}">Setup</a>
                <a href="{{ route('templates.index') }}">Contracts</a>
                <a href="{{ route('templates.complete') }}">Send a contract</a>
                <a href="{{ route('audit.index') }}">Audit</a>
            @endif
            <span class="who">{{ auth()->user()->name }} · {{ \App\Hr\Roles::labelFor(auth()->user()) }}</span>
            <form action="{{ route('logout') }}" method="post">@csrf<button class="quiet" type="submit">Sign out</button></form>
        </nav>
    @endauth
</header>
<main class="wrap">
    @if($errors->any())
        <div class="card error">{{ $errors->first() }}</div>
    @endif
    @if(session('status'))
        <div class="card">{{ session('status') }}</div>
    @endif
    @if(session('invite_url'))
        <div class="card">Invite link, shown once: <a href="{{ session('invite_url') }}">{{ session('invite_url') }}</a></div>
    @endif
    @yield('content')
</main>
</body>
</html>
