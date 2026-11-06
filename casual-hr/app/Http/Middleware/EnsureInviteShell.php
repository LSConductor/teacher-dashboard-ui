<?php

namespace App\Http\Middleware;

use App\Models\Person;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInviteShell
{
    public function handle(Request $request, Closure $next): Response
    {
        $personId = $request->session()->get('invite_person_id');
        $person = $personId
            ? Person::query()->select(['id', 'school_id', 'preferred_name', 'employment_status'])->find($personId)
            : null;

        if (! $person) {
            abort(403, 'This induction shell is closed.');
        }

        $request->attributes->set('invitePerson', $person);

        return $next($request);
    }
}
