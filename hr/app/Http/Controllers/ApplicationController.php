<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\School;
use App\Services\Audit;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function create()
    {
        return view('apply.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'preferred_name' => ['required', 'string', 'max:160'],
            'legal_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160'],
            'mobile' => ['required', 'string', 'max:40'],
            'neighbourhood' => ['required', 'string', 'max:80'],
            'stages' => ['required', 'array', 'min:1'],
            'stages.*' => ['string'],
            'right_to_work' => ['required', 'string', 'max:2000'],
            'heard_about' => ['nullable', 'string', 'max:160'],
        ]);

        $school = School::query()->where('slug', 'living-school')->firstOrFail();
        $existing = Person::query()->where('school_id', $school->id)->where('email', $data['email'])->first();
        if ($existing) {
            return back()->withErrors([
                'email' => 'That email is already on the list. Ask HR for the invite link.',
            ])->withInput();
        }

        $person = Person::create([
            'school_id' => $school->id,
            'preferred_name' => $data['preferred_name'],
            'legal_name' => $data['legal_name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'],
            'neighbourhood' => $data['neighbourhood'],
            'stages' => $data['stages'],
            'right_to_work' => $data['right_to_work'],
            'heard_about' => $data['heard_about'] ?? null,
            'status' => 'applicant',
        ]);
        $person->accreditation()->create(['school_id' => $school->id, 'alignment' => 'pending']);
        $person->wwcc()->create(['school_id' => $school->id, 'outcome' => 'pending']);
        Audit::record('application.received', $person);

        return redirect()->route('apply')->with('status', 'Thank you. HR has this application and will send one invite to '.$person->email.'.');
    }
}
