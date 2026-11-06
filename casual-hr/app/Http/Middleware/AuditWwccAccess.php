<?php

namespace App\Http\Middleware;

use App\Hr\AuditLog;
use App\Models\Person;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditWwccAccess
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $person = $request->route('person');
        $person = $person instanceof Person ? $person : null;

        if (! $user || $user->role !== User::HR) {
            $this->audit->record($user, 'wwcc.open_denied', $person, $person, [
                'route' => 'hr.people.wwcc',
            ], $request);

            abort(403, 'WWCC records are HR only.');
        }

        $this->audit->record($user, 'wwcc.viewed', $person, $person, [
            'route' => 'hr.people.wwcc',
        ], $request);

        return $next($request);
    }
}
