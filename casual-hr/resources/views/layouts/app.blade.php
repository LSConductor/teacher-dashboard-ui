<!DOCTYPE html>
<html lang="en-AU">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Casual HR' }}</title>
    <link rel="stylesheet" href="{{ asset('casual.css') }}">
</head>
<body>
    <div class="wrap">
        <header class="site">
            <div>
                <h1>{{ $title ?? 'Casual HR' }}</h1>
                <p>{{ $intro ?? 'Living School casual list' }}</p>
            </div>
            @auth
                <nav>
                    @if (auth()->user()->isHr() || auth()->user()->isConductor())
                        <a href="{{ route('hr.index') }}">HR list</a>
                        <a href="{{ route('hr.audit') }}">Audit</a>
                    @endif
                    @if (auth()->user()->isDirector())
                        <a href="{{ route('director.index') }}">Call list</a>
                    @endif
                    @if (auth()->user()->isOffice())
                        <a href="{{ route('office.index') }}">Cover</a>
                    @endif
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="secondary" type="submit">Log out</button>
                    </form>
                </nav>
            @endauth
        </header>

        <div class="banner">
            <strong>Local sign-in stands in for the Staff Portal Google session.</strong>
            It lets this app be exercised without school credentials. Production login stays the Staff Portal. This app does not hold school OAuth secrets.
        </div>

        @if (session('status'))
            <p class="status">{{ session('status') }}</p>
        @endif
        @if (session('invite_url'))
            <p class="status">Invite link, shown once: <a href="{{ session('invite_url') }}">{{ session('invite_url') }}</a></p>
        @endif
        @if (session('note_ignored'))
            <p class="error">{{ session('note_ignored') }}</p>
        @endif
        @if ($errors->any())
            <div class="error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <main>
            @yield('content')
        </main>

        <footer class="site">
            Dates use Australia/Sydney. School day 9:00–3:15. Office (02) 5632 1218.
            A booking may store a WordPress program id from the staff portal. The program stays in WordPress.
        </footer>
    </div>
</body>
</html>
