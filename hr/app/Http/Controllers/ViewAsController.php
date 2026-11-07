<?php

namespace App\Http\Controllers;

use App\Hr\Roles;
use App\Hr\ViewAs;
use App\Models\Person;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ViewAsController extends Controller
{
    public function start(Request $request, Person $person)
    {
        $casual = ViewAs::casualFor($person);
        if (! $casual) {
            return back()->with('status', $person->preferred_name.' has no sign-in yet. Send an invite, then you can open their screens.');
        }

        Audit::record('view.opened', $person, ['casual_user_id' => $casual->id]);
        $request->session()->put(ViewAs::BY, $request->user()->id);
        $request->session()->put(ViewAs::PERSON, $person->id);
        Auth::login($casual);

        return redirect()->route('start');
    }

    public function stop(Request $request)
    {
        $admin = User::query()->find($request->session()->get(ViewAs::BY));
        $current = $request->user();
        abort_unless(
            $admin && $current && Roles::manages($admin) && (int) $admin->school_id === (int) $current->school_id,
            403
        );

        $personId = (int) $request->session()->get(ViewAs::PERSON);
        ViewAs::forget($request);
        Auth::login($admin);

        $person = Person::query()->where('school_id', $admin->school_id)->find($personId);
        if ($person) {
            Audit::record('view.closed', $person);
        }

        $target = $person
            ? route('casuals.show', $person)
            : route('casuals.index');

        return redirect()->to($target)->with('status', 'You are back on your own desk.');
    }
}
