<?php

namespace App\Http\Controllers;

use App\Services\GoogleSignIn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GoogleController extends Controller
{
    public function redirect(Request $request, GoogleSignIn $google)
    {
        if (! $google->ready()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google sign-in needs GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET on the server. The password on this page still works for this build.',
            ]);
        }

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        return redirect()->away($google->authorizationUrl($state));
    }

    public function callback(Request $request, GoogleSignIn $google)
    {
        if (! $google->ready()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google sign-in needs GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET on the server.',
            ]);
        }

        $state = (string) $request->session()->pull('google_oauth_state');
        if ($state === '' || ! hash_equals($state, (string) $request->query('state'))) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google sign-in did not complete. Try again.',
            ]);
        }

        try {
            $profile = $google->profile((string) $request->query('code'));
        } catch (\Throwable) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google did not confirm that account. Try again, or use the password on this page.',
            ]);
        }

        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        $domain = config('services.google.hosted_domain', 'living.school');
        $verified = filter_var($profile['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $verified || ! str_ends_with($email, '@'.$domain)) {
            return redirect()->route('login')->withErrors([
                'email' => 'Use a Living School Google account (@'.$domain.').',
            ]);
        }

        $user = $google->findUser((string) ($profile['sub'] ?? ''), $email);
        if (! $user) {
            return redirect()->route('login')->withErrors([
                'email' => 'That school Google account is not on the casual list. Ask HR to use the same email on the person. The demo password still signs in the sample accounts.',
            ]);
        }

        $google->remember($user, $profile['sub'] ?? null);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('casuals.index'));
    }
}
