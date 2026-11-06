<?php

namespace App\Http\Middleware;

use App\Models\Person;
use App\Tenancy\SchoolContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentSchool
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(SchoolContext::class);
        $user = $request->user();

        if ($user?->school) {
            $context->use($user->school);
            $request->session()->put('school_id', $user->school_id);
        } elseif ($personId = $request->session()->get('invite_person_id')) {
            $person = Person::withoutGlobalScope('school')->with('school')->find($personId);

            if ($person?->school) {
                $context->use($person->school);
                $request->session()->put('school_id', $person->school_id);
            }
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $user = $request->user();

        if (! $user || config('session.driver') !== 'database') {
            return;
        }

        DB::table('sessions')->where('id', $request->session()->getId())->update([
            'school_id' => $user->school_id,
        ]);
    }
}
