<?php

namespace App\Http\Controllers;

use App\Models\InductionModule;
use App\Models\Person;
use App\Services\Audit;
use Illuminate\Http\Request;

class InductionController extends Controller
{
    public function pack(Person $person)
    {
        $this->authorize('view', $person);
        $modules = InductionModule::query()
            ->where('school_id', $person->school_id)
            ->whereIn('phase', ['day_one', 'later'])
            ->orderBy('sort')
            ->get();
        $person->load('acknowledgements');

        return view('induction.pack', [
            'person' => $person,
            'modules' => $modules,
        ]);
    }

    public function show(Person $person, InductionModule $module)
    {
        $this->authorize('view', $person);
        abort_unless($module->school_id === $person->school_id, 404);
        $done = $person->acknowledgements()
            ->where('module_id', $module->id)
            ->where('module_version', '>=', $module->version)
            ->first();

        return view('induction.show', [
            'person' => $person,
            'module' => $module,
            'done' => $done,
            'canAcknowledge' => in_array(request()->user()->role, ['hr', 'conductor'], true)
                || (int) request()->user()->person_id === (int) $person->id,
        ]);
    }

    public function acknowledge(Request $request, Person $person, InductionModule $module)
    {
        $this->authorize('view', $person);
        $user = $request->user();
        $allowed = in_array($user->role, ['hr', 'conductor'], true)
            || ((int) $user->person_id === (int) $person->id);
        abort_unless($allowed, 403);
        abort_unless($module->school_id === $person->school_id, 404);

        $quiz = $module->quiz ?? [];
        if ($user->role === 'casual' && $quiz !== []) {
            $answers = $request->input('answers', []);
            foreach ($quiz as $index => $question) {
                if ((int) ($answers[$index] ?? -1) !== (int) $question['answer']) {
                    return back()
                        ->withErrors(['answers' => 'One answer does not match the page. Read it again and try the check.'])
                        ->withInput();
                }
            }
        }

        $person->acknowledgements()->updateOrCreate(
            ['module_id' => $module->id, 'module_version' => $module->version],
            ['school_id' => $person->school_id, 'acknowledged_at' => now()]
        );
        Audit::record('induction.acknowledged', $person, [
            'module' => $module->title,
            'version' => $module->version,
            'name' => $person->preferred_name,
        ]);

        return back();
    }
}
