<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\WwccCheck;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ComplianceController extends Controller
{
    public function wwcc(Request $request, Person $person)
    {
        $this->authorize('viewSensitive', $person);
        if (! \App\Hr\Roles::manages($request->user()) && ! \App\Hr\Roles::isCasual($request->user())) {
            abort(403);
        }
        if (\App\Hr\Roles::isCasual($request->user()) && (int) $request->user()->person_id !== (int) $person->id) {
            abort(403);
        }

        $data = $request->validate([
            'wwcc_number' => ['required', 'string', 'max:40'],
            'name_on_check' => ['required', 'string', 'max:160'],
            'date_of_birth' => ['required', 'date'],
            'outcome' => ['required', 'in:pending,cleared,barred,interim_bar,expired'],
            'expires_on' => ['nullable', 'date'],
        ]);

        if (WwccCheck::looksLikeApplicationNumber($data['wwcc_number'])) {
            throw ValidationException::withMessages([
                'wwcc_number' => 'An APP number is not a clearance. HR must verify a paid NSW WWCC before this person can teach.',
            ]);
        }

        if ($data['outcome'] === 'cleared' && \App\Hr\Roles::isCasual($request->user())) {
            $data['outcome'] = 'pending';
        }

        if ($data['outcome'] === 'cleared' && empty($data['expires_on'])) {
            throw ValidationException::withMessages([
                'expires_on' => 'A verified clearance needs an expiry date.',
            ]);
        }

        $wwcc = $person->wwcc()->firstOrNew(['school_id' => $person->school_id]);
        $wwcc->fill($data);
        if ($data['outcome'] === 'cleared') {
            $wwcc->verified_at = now();
            $wwcc->verified_by = $request->user()->id;
        }
        $wwcc->school_id = $person->school_id;
        $wwcc->save();
        Audit::record('wwcc.updated', $person, ['outcome' => $wwcc->outcome]);

        return back();
    }

    public function accreditation(Request $request, Person $person)
    {
        $this->authorize('updateFile', $person);
        $data = $request->validate([
            'nesa_number' => ['nullable', 'string', 'max:40'],
            'level' => ['nullable', 'in:conditional,provisional,proficient,highly_accomplished,lead'],
            'status' => ['required', 'in:active,leave_of_absence,non_practising,suspended,cancelled'],
            'maintenance_end' => ['nullable', 'date'],
            'fee_year' => ['nullable', 'string', 'max:8'],
            'casual_evidence_on_file' => ['sometimes', 'boolean'],
        ]);
        $data['casual_evidence_on_file'] = $request->boolean('casual_evidence_on_file');
        $data['etams_checked_at'] = now();
        $data['checked_by'] = $request->user()->id;
        $accreditation = $person->accreditation()->firstOrNew(['school_id' => $person->school_id]);
        $accreditation->fill($data);
        $accreditation->school_id = $person->school_id;
        $accreditation->save();
        Audit::record('accreditation.checked', $person, ['level' => $accreditation->level, 'status' => $accreditation->status]);

        return back();
    }

    public function alignment(Request $request, Person $person)
    {
        abort_unless($request->user()->role === 'conductor', 403);
        $this->authorize('view', $person);
        $data = $request->validate(['alignment' => ['required', 'in:approved,declined']]);
        $accreditation = $person->accreditation()->firstOrNew(['school_id' => $person->school_id]);
        $accreditation->alignment = $data['alignment'];
        $accreditation->alignment_decided_at = now();
        $accreditation->alignment_decided_by = $request->user()->id;
        $accreditation->school_id = $person->school_id;
        $accreditation->save();
        Audit::record('alignment.decided', $person, ['alignment' => $data['alignment']]);

        return back();
    }
}
