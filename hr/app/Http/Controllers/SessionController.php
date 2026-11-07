<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Those details do not match a staff account.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('casuals.index'));
    }

    public function destroy(Request $request)
    {
        if (\App\Hr\ViewAs::active($request)) {
            return app(ViewAsController::class)->stop($request);
        }

        Auth::logout();

        try {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } catch (\Throwable) {
            // The sign-in is already cleared. A session write must not turn logout into a 500.
        }

        return redirect()->route('login');
    }
}
