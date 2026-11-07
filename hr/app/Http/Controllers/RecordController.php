<?php

namespace App\Http\Controllers;

use App\Hr\Roles;
use App\Models\Person;
use App\Models\StandardsNotice;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        $standards = $request->input('standards');
        if (is_array($standards)) {
            $request->merge(['standards' => implode(', ', array_map('strval', $standards))]);
        }
        $data = $request->validate([
            'occurred_on' => ['required', 'date'],
            'name' => ['required', 'string', 'max:160'],
            'provider' => ['nullable', 'string', 'max:160'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:100'],
            'standards' => ['required', 'string', 'max:80'],
            'activity_type' => ['nullable', 'string', 'max:80'],
        ]);
        $entry = $person->pdEntries()->create($data + ['school_id' => $person->school_id]);
        Audit::record('pd.added', $entry, ['hours' => $entry->hours]);

        return back();
    }

    public function goal(Request $request, Person $person)
    {
        $this->writePerformance($request, $person);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'descriptors' => ['nullable', 'string', 'max:80'],
        ]);
        $record = $person->performanceRecords()->updateOrCreate(
            ['kind' => 'goal'],
            $data + $this->performanceMeta($request, $person, 'goal')
        );
        Audit::record('goal.saved', $record);

        return back();
    }

    public function observation(Request $request, Person $person)
    {
        $this->writePerformance($request, $person);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'next_step' => ['required', 'string', 'max:2000'],
            'descriptors' => ['nullable', 'string', 'max:80'],
        ]);
        $record = $person->performanceRecords()->updateOrCreate(
            ['kind' => 'observation'],
            $data + $this->performanceMeta($request, $person, 'observation')
        );
        Audit::record('observation.saved', $record);

        return back();
    }

    public function annual(Person $person)
    {
        $this->authorize('view', $person);
        $person->load('annualReview.author');
        $user = request()->user();

        return view('casuals.annual', [
            'person' => $person,
            'annual' => $person->annualReview,
            'canWrite' => in_array(Roles::kind($user), ['neighbourhood', 'support', 'manage'], true),
        ]);
    }

    public function saveAnnual(Request $request, Person $person)
    {
        $this->writePerformance($request, $person);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'students_did' => ['required', 'string', 'max:2000'],
            'keep_doing' => ['required', 'string', 'max:2000'],
            'change_next' => ['required', 'string', 'max:2000'],
        ]);
        $record = $person->performanceRecords()->updateOrCreate(
            ['kind' => 'annual'],
            $data + $this->performanceMeta($request, $person, 'annual')
        );
        Audit::record('annual.saved', $record);

        return redirect()->route('casuals.annual', $person);
    }

    public function conduct(Request $request, Person $person)
    {
        $this->authorize('updateFile', $person);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['allegation', 'child_protection', 'complaint'])],
            'noted_on' => ['required', 'date'],
            'location' => ['required', 'string', 'max:200'],
        ]);
        $flag = $person->conductFlags()->create($data + [
            'school_id' => $person->school_id,
            'recorded_by' => $request->user()->id,
        ]);
        Audit::record('conduct.flagged', $flag, ['kind' => $flag->kind]);

        return back()->with('status', 'The file location is on the record. The detail stays where you named.');
    }

    public function standards(Request $request, Person $person)
    {
        $this->authorize('updateFile', $person);
        $data = $request->validate(['told_on' => ['required', 'date']]);
        $told = \Carbon\Carbon::parse($data['told_on'], 'Australia/Sydney')->startOfDay();
        $notice = $person->standardsNotices()->create([
            'school_id' => $person->school_id,
            'told_on' => $told->toDateString(),
            'due_on' => $told->copy()->addDays(28)->toDateString(),
            'recorded_by' => $request->user()->id,
        ]);
        Audit::record('standards.told', $notice);

        return back()->with('status', 'The 28 days start from '.$told->format('j M Y').'.');
    }

    public function standardsReply(Request $request, Person $person, StandardsNotice $notice)
    {
        $this->authorize('viewSensitive', $person);
        abort_unless((int) $notice->person_id === (int) $person->id, 404);
        abort_if($notice->reply_on, 422, 'A reply is already on the file.');
        $data = $request->validate(['reply' => ['required', 'string', 'max:4000']]);
        $notice->update([
            'reply' => $data['reply'],
            'reply_on' => now()->timezone('Australia/Sydney')->toDateString(),
        ]);
        Audit::record('standards.replied', $notice);

        return back()->with('status', 'The reply is on the file.');
    }

    private function writePerformance(Request $request, Person $person): void
    {
        $this->authorize('view', $person);
        abort_unless(in_array(Roles::kind($request->user()), ['neighbourhood', 'support', 'manage'], true), 403);
    }

    private function performanceMeta(Request $request, Person $person, string $kind): array
    {
        return [
            'school_id' => $person->school_id,
            'kind' => $kind,
            'recorded_on' => now()->timezone('Australia/Sydney')->toDateString(),
            'author_id' => $request->user()->id,
        ];
    }
}
