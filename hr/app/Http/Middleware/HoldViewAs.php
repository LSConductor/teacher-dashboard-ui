<?php

namespace App\Http\Middleware;

use App\Hr\Roles;
use App\Hr\ViewAs;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HoldViewAs
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $request->session()->has(ViewAs::BY)) {
            view()->share('viewingAs', null);

            return $next($request);
        }

        $admin = User::query()->find($request->session()->get(ViewAs::BY));
        $current = $request->user();
        $personId = (int) $request->session()->get(ViewAs::PERSON);
        $sound = $admin
            && $current
            && Roles::manages($admin)
            && (int) $admin->school_id === (int) $current->school_id
            && Roles::isCasual($current)
            && (int) $current->person_id === $personId;

        if (! $sound) {
            view()->share('viewingAs', null);
            ViewAs::forget($request);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && ! $request->routeIs('view-as.stop', 'logout')) {
            return back()->withErrors([
                'view' => 'You are only looking. Nothing is saved on their screens.',
            ]);
        }

        view()->share('viewingAs', $current->name);

        return $next($request);
    }
}
