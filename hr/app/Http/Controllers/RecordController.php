<?php

namespace App\Http\Controllers;

use App\Hr\Roles;
use App\Models\Person;
use App\Services\Audit;
use Illuminate\Http\Request;

class RecordController extends Controller
{
    public function unavailability(Request $request, Person $person)
    {
        $this->authorize('view', $person);
        $user = $request->user();
        abort_unless(
            in_array(Roles::kind($user), ['manage', 'neighbourhood'], true) || (int) $user->person_id === (int) $person->id,
            403
        );
        $data = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $row = $person->unavailabilities()->create($data + [
            'school_id' => $person->school_id,
            'status' => 'accepted',
        ]);
        Audit::record('unavailability.recorded', $row);

        return back();
    }

    public function dayNote(Request $request, Person $person)
    {
        $this->authorize('view', $person);
        abort_unless(in_array(Roles::kind($request->user()), ['neighbourhood', 'support', 'manage'], true), 403);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'descriptors' => ['nullable', 'string', 'max:80'],
        ]);
        $note = $person->dayNotes()->create($data + [
            'school_id' => $person->school_id,
            'author_id' => $request->user()->id,
        ]);
        Audit::record('day_note.added', $note);

        return back();
    }

    public function pd(Request $request, Person $person)
    {
        $this->authorize('view', $person);
        $user = $request->user();
        abort_unless(
            in_array(Roles::kind($user), ['manage', 'support'], true) || (int) $user->person_id === (int) $person->id,
            403
        );
        $data = $request->validate([
            'occurred_on' => ['required', 'date'],
            'name' => ['required', 'string', 'max:160'],
            'provider' => ['nullable', 'string', 'max:160'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:100'],
            'standards' => ['nullable', 'string', 'max:80'],
            'activity_type' => ['nullable', 'string', 'max:80'],
        ]);
        $entry = $person->pdEntries()->create($data + ['school_id' => $person->school_id]);
        Audit::record('pd.added', $entry, ['hours' => $entry->hours]);

        return back();
    }
}
