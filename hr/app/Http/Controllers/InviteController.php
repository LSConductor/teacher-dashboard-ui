<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Models\Person;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class InviteController extends Controller
{
    public function store(Request $request, Person $person)
    {
        $this->authorize('updateFile', $person);
        $token = Str::random(40);
        Invite::create([
            'school_id' => $person->school_id,
            'person_id' => $person->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ]);
        Audit::record('invite.created', $person);

        return back()->with('invite_url', route('invites.accept', $token));
    }

    public function accept(string $token)
    {
        $invite = Invite::query()->where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_if($invite->used_at || $invite->expires_at->isPast(), 410);

        return view('invites.accept', ['invite' => $invite, 'token' => $token]);
    }

    public function complete(Request $request, string $token)
    {
        $invite = Invite::query()->with('person')->where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_if($invite->used_at || $invite->expires_at->isPast(), 410);
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $person = $invite->person;
        $user = User::query()->where('email', $person->email)->first();
        if ($user) {
            $user->update([
                'password' => $data['password'],
                'person_id' => $person->id,
                'role' => 'casual',
                'school_id' => $person->school_id,
                'neighbourhood' => $person->neighbourhood,
            ]);
        } else {
            $user = User::create([
                'name' => $person->preferred_name,
                'email' => $person->email,
                'password' => $data['password'],
                'school_id' => $person->school_id,
                'role' => 'casual',
                'person_id' => $person->id,
                'neighbourhood' => $person->neighbourhood,
            ]);
        }
        $invite->update(['used_at' => now()]);
        Audit::record('invite.accepted', $person);
        Auth::login($user);

        return redirect()->route('casuals.show', $person);
    }
}
