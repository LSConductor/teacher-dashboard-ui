<?php

namespace App\Http\Controllers;

use App\Models\InductionModule;
use App\Models\Person;
use App\Services\Audit;
use Illuminate\Http\Request;

class InductionController extends Controller
{
    public function acknowledge(Request $request, Person $person, InductionModule $module)
    {
        $this->authorize('view', $person);
        $user = $request->user();
        $allowed = in_array($user->role, ['hr', 'conductor'], true)
            || ((int) $user->person_id === (int) $person->id);
        abort_unless($allowed, 403);
        abort_unless($module->school_id === $person->school_id, 404);

        $person->acknowledgements()->updateOrCreate(
            ['module_id' => $module->id, 'module_version' => $module->version],
            ['school_id' => $person->school_id, 'acknowledged_at' => now()]
        );
        Audit::record('induction.acknowledged', $person, ['module' => $module->title, 'version' => $module->version]);

        return back();
    }
}
