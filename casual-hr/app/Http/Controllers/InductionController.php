<?php

namespace App\Http\Controllers;

use App\Hr\AuditLog;
use App\Models\Invite;
use App\Models\Person;
use App\Models\School;
use App\Tenancy\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InductionController extends Controller
{
    public function __construct(private readonly AuditLog $audit) {}

    public function open(Request $request, string $token): RedirectResponse|View|Response
    {
        $consumed = false;
        $invite = DB::transaction(function () use ($token, &$consumed) {
            $found = Invite::withoutGlobalScope('school')
                ->where('token_hash', Invite::hashToken($token))
                ->lockForUpdate()
                ->first();

            if (! $found || $found->isSpent()) {
                return $found;
            }

            $found->update(['used_at' => now()]);
            $consumed = true;

            return $found;
        });

        if (! $invite) {
            abort(404);
        }

        if (! $consumed) {
            return response()->view('induction.invalid', [], 410);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->regenerate();
        $request->session()->put('invite_person_id', $invite->person_id);
        $request->session()->put('school_id', $invite->school_id);

        app(SchoolContext::class)->use(School::find($invite->school_id));
        $person = Person::withoutGlobalScope('school')->find($invite->person_id);
        $this->audit->record(null, 'invite.consumed', $invite, $person, [
            'person_id' => $invite->person_id,
        ]);

        return redirect()->route('induction.shell');
    }

    public function shell(Request $request): View
    {
        return view('induction.shell', [
            'person' => $request->attributes->get('invitePerson'),
        ]);
    }

    public function leave(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
