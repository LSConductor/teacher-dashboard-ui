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
        .file-tab { position: absolute; opacity: 0; width: 1px; height: 1px; }
        .file-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 14px; }
        .file-tabs label { margin: 0; cursor: pointer; border: 1px solid var(--line); border-radius: 999px; padding: 8px 14px; background: #fff; font-weight: 650; }
        .file-card .panel { display: none; }
        .file-open .panel { display: block; }
        .file-card #ft-person:checked ~ .panel-person,
        .file-card #ft-clearances:checked ~ .panel-clearances,
        .file-card #ft-day:checked ~ .panel-day,
        .file-card #ft-learning:checked ~ .panel-learning,
        .file-card #ft-paper:checked ~ .panel-paper { display: block; }
        .file-card #ft-person:checked ~ .file-tabs label[for="ft-person"],
        .file-card #ft-clearances:checked ~ .file-tabs label[for="ft-clearances"],
        .file-card #ft-day:checked ~ .file-tabs label[for="ft-day"],
        .file-card #ft-learning:checked ~ .file-tabs label[for="ft-learning"],
        .file-card #ft-paper:checked ~ .file-tabs label[for="ft-paper"] { background: var(--navy); color: #fff; }
        .alarm-pill { display: inline-block; margin-left: 6px; font-size: 12px; font-weight: 750; padding: 2px 6px; border-radius: 999px; }
        .alarm-pill.amber { background: var(--amber-bg); color: var(--amber); }
        .alarm-pill.red { background: var(--red-bg); color: var(--red); }
        .alarm-row { margin: 0 0 8px; }
        .sheet { background: #fff; border: 1px solid var(--line); border-radius: 14px; overflow: hidden; margin: 0 0 18px; max-width: 740px; }
        .sheet-head { background: var(--navy); color: #fff; padding: 22px 32px 18px; }
        .sheet-head span { display: block; font-size: 12px; letter-spacing: .16em; text-transform: uppercase; opacity: .72; }
        .sheet-head strong { display: block; margin-top: 4px; font-size: 20px; font-weight: 650; }
        .sheet-body { padding: 28px 32px 8px; font-family: Georgia, "Iowan Old Style", Palatino, "Palatino Linotype", serif; font-size: 18px; line-height: 1.55; }
        .sheet-body h2 { font-family: "Segoe UI", system-ui, sans-serif; font-size: 28px; line-height: 1.2; font-weight: 680; margin: 0 0 8px; }
        .sheet-body .sheet-meta { font-family: "Segoe UI", system-ui, sans-serif; font-size: 14px; color: var(--muted); margin: 0 0 18px; }
        .sheet-body p { margin: 0 0 14px; }
        .sheet-foot { padding: 4px 32px 22px; color: var(--muted); font-size: 13px; }
        .day-code { font-size: 42px; letter-spacing: .28em; font-weight: 750; margin: 8px 0 0; }
        .dayboard { max-width: 760px; }
        .dayhero { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 26px 28px 20px; margin: 0 0 22px; }
        .dayhero .kicker { margin: 0 0 6px; font-size: 12px; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); font-weight: 700; }
        .dayhero h1 { font-size: 42px; line-height: 1.05; margin: 0 0 8px; }
        .quiet-links { display: flex; flex-wrap: wrap; gap: 8px 18px; margin: 16px 0 8px; }
        .quiet-links a { color: var(--navy); font-weight: 650; }
        details.done-list summary { cursor: pointer; font-weight: 650; margin: 18px 0 12px; }
        nav a.minor { font-weight: 500; opacity: .8; }
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
                <a href="{{ route('start') }}">Today</a>
                <a href="{{ route('today') }}">Sign on</a>
                <a href="{{ route('people') }}">People</a>
            @else
                <a href="{{ route('today') }}">Today</a>
                @if(in_array(\App\Hr\Roles::kind(auth()->user()), ['manage', 'front_desk', 'neighbourhood'], true))
                    <a href="{{ route('watch') }}">Watch</a>
                @endif
                <a href="{{ route('people') }}">People</a>
                <a href="{{ route('casuals.index') }}">List</a>
                <a class="minor" href="{{ route('access') }}">Access</a>
                @if(in_array(\App\Hr\Roles::kind(auth()->user()), ['manage', 'neighbourhood'], true))
                    <a class="minor" href="{{ route('partners.index') }}">Partners</a>
                @endif
                @if(\App\Hr\Roles::manages(auth()->user()))
                    <a class="minor" href="{{ route('setup') }}">Setup</a>
                    <a class="minor" href="{{ route('templates.index') }}">Contracts</a>
                    <a class="minor" href="{{ route('templates.complete') }}">Send a contract</a>
                    <a class="minor" href="{{ route('audit.index') }}">Audit</a>
                @endif
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
