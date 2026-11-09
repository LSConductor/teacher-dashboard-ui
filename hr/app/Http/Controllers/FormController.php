<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Services\Audit;
use Illuminate\Http\Request;

class FormController extends Controller
{
    public function index(Person $person)
    {
        $this->open($person);

        return view('forms.index', ['person' => $person->load(['accreditation', 'wwcc'])]);
    }

    public function personal(Person $person)
    {
        $this->open($person);

        return view('forms.personal', compact('person'));
    }

    public function savePersonal(Request $request, Person $person)
    {
        $this->open($person);
        $data = $request->validate([
            'postal_address' => ['required', 'string', 'max:255'],
            'emergency_contact_name' => ['required', 'string', 'max:160'],
            'emergency_contact_mobile' => ['required', 'string', 'max:40'],
            'adjustments' => ['nullable', 'string'],
            'payroll_declaration_lodged' => ['sometimes', 'boolean'],
        ]);
        $data['payroll_declaration_lodged'] = $request->boolean('payroll_declaration_lodged');
        $person->update($data);
        Audit::record('form.personal', $person);

        return redirect()->route('casuals.forms', $person);
    }

    public function accreditation(Person $person)
    {
        $this->open($person);

        return view('forms.accreditation', ['person' => $person->load('accreditation')]);
    }

    public function wwcc(Person $person)
    {
        $this->open($person);

        return view('forms.wwcc', ['person' => $person->load('wwcc')]);
    }

    public function availability(Person $person)
    {
        $this->open($person);

        return view('forms.availability', compact('person'));
    }

    public function saveAvailability(Request $request, Person $person)
    {
        $this->open($person);
        $data = $request->validate([
            'available_days' => ['required', 'array', 'min:1'],
            'available_days.*' => ['string'],
            'neighbourhoods' => ['nullable', 'array'],
            'neighbourhoods.*' => ['string'],
            'stages' => ['nullable', 'array'],
            'stages.*' => ['string'],
            'earliest_start' => ['required', 'string', 'max:20'],
        ]);
        $person->update($data);
        Audit::record('form.availability', $person);

        return redirect()->route('casuals.forms', $person);
    }

    private function open(Person $person): void
    {
        $this->authorize('view', $person);
        $user = request()->user();
        abort_unless(\App\Hr\Roles::manages($user) || \App\Hr\Roles::isCasual($user), 403);
    }
}
