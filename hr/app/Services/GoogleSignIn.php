<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class GoogleSignIn
{
    public function ready(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirectUri(): string
    {
        return config('services.google.redirect') ?: url('/auth/google/callback');
    }

    public function authorizationUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'hd' => config('services.google.hosted_domain', 'living.school'),
            'prompt' => 'select_account',
        ]);
    }

    /**
     * @return array{sub?: string, email?: string, email_verified?: bool}
     */
    public function profile(string $code): array
    {
        $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ])->throw()->json();

        return Http::withToken($token['access_token'])
            ->get('https://www.googleapis.com/oauth2/v3/userinfo')
            ->throw()
            ->json();
    }

    public function findUser(string $sub, string $email): ?User
    {
        if ($sub !== '' && $this->hasColumn()) {
            $found = User::query()->where('google_sub', $sub)->first();
            if ($found) {
                return $found;
            }
        }

        return User::query()->whereRaw('lower(email) = ?', [$email])->first();
    }

    public function remember(User $user, ?string $sub): void
    {
        if (! filled($sub) || ! $this->hasColumn()) {
            return;
        }

        $user->google_sub = $sub;
        $user->save();
    }

    public function hasColumn(): bool
    {
        return Schema::hasColumn('users', 'google_sub');
    }
}
