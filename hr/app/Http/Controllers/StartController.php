<?php

namespace App\Http\Controllers;

use App\Models\InductionModule;
use App\Models\Invite;
use App\Models\Person;
use App\Models\User;
use App\Services\Audit;
use App\Services\ClearToTeach;
use Illuminate\Http\Request;

class StartController extends Controller
{
    public function show(Request $request, ClearToTeach $gate)
    {
        $invite = $this->inviteFromCode($request->string('code')->toString());
        $state = $this->inviteState($invite);

        if ($request->user() && $invite && ! $this->ownsInvite($request->user(), $invite)) {
            return view('induction.start', [
                'mode' => 'wrong',
                'personName' => $request->user()->can('view', $invite->person)
                    ? $invite->person->preferred_name
                    : null,
            ]);
        }

        if ($request->user() && $state === 'valid' && $this->ownsInvite($request->user(), $invite)) {
            $invite->update(['used_at' => now()]);
            Audit::record('invite.returned', $invite->person);

            return redirect()->route('start');
        }

        if (! $request->user()) {
            redirect()->setIntendedUrl($request->fullUrl());

            return view('induction.start', [
                'mode' => 'gate',
                'inviteState' => $state,
                'invite' => $state === 'valid' ? $invite : null,
                'code' => $request->string('code')->toString(),
            ]);
        }

        $user = $request->user();
        if ($user->role !== 'casual' || ! $user->person_id) {
            return view('induction.start', ['mode' => 'staff']);
        }

        $person = Person::query()
            ->where('school_id', $user->school_id)
            ->with(['accreditation', 'wwcc', 'contracts.template', 'acknowledgements', 'mentor', 'bookings'])
            ->findOrFail($user->person_id);

        $modules = InductionModule::query()
            ->where('school_id', $person->school_id)
            ->orderBy('sort')
            ->get(['id', 'phase', 'version', 'title']);
        $acked = $person->acknowledgements->mapWithKeys(
            fn ($row) => [$row->module_id => (int) $row->module_version]
        );
        $done = function ($phase) use ($modules, $acked) {
            return $modules->where('phase', $phase)->filter(
                fn ($module) => (int) ($acked[$module->id] ?? 0) >= (int) $module->version
            )->count();
        };
        $dayTotal = $modules->where('phase', 'day_one')->count();
        $laterTotal = $modules->where('phase', 'later')->count();
        $director = User::query()
            ->where('school_id', $person->school_id)
            ->where('role', 'director')
            ->where('neighbourhood', $person->neighbourhood)
            ->first();
        $signed = $person->contracts->contains(
            fn ($contract) => $contract->status === 'signed'
                && $contract->template
                && $contract->template->key === 'casual-pool'
        );

        return view('induction.start', [
            'mode' => 'desk',
            'person' => $person,
            'gate' => $gate->assess($person),
            'director' => $director,
            'today' => $person->bookings->first(fn ($booking) => $booking->booked_on->isSameDay(now())),
            'dayDone' => $done('day_one'),
            'dayTotal' => $dayTotal,
            'laterDone' => $done('later'),
            'laterTotal' => $laterTotal,
            'steps' => [
                [
                    'title' => 'Personal and emergency',
                    'done' => filled($person->postal_address) && filled($person->emergency_contact_name),
                    'detail' => $person->emergency_contact_name
                        ? 'Emergency contact is on the file.'
                        : 'Address and an emergency contact are still needed.',
                    'href' => route('casuals.forms.personal', $person),
                    'action' => 'Open the form',
                ],
                [
                    'title' => 'Accreditation',
                    'done' => filled($person->accreditation->nesa_number ?? null),
                    'detail' => trim(ucfirst($person->accreditation->level ?? 'No level yet').' · alignment '.($person->accreditation->alignment ?? 'pending')),
                    'href' => route('casuals.forms.accreditation', $person),
                    'action' => 'Open the form',
                ],
                [
                    'title' => 'WWCC details',
                    'done' => ($person->wwcc->outcome ?? 'pending') === 'cleared',
                    'detail' => ($person->wwcc->outcome ?? 'pending') === 'cleared'
                        ? 'Paid clearance is verified. The number stays on the form, not on this desk.'
                        : 'Add the clearance for HR to verify. An APP number is not a clearance.',
                    'href' => route('casuals.forms.wwcc', $person),
                    'action' => 'Open the form',
                ],
                [
                    'title' => 'Availability',
                    'done' => filled($person->available_days) && filled($person->earliest_start),
                    'detail' => $person->earliest_start
                        ? 'Earliest start '.$person->earliest_start.'.'
                        : 'Days you can work are not recorded yet.',
                    'href' => route('casuals.forms.availability', $person),
                    'action' => 'Open the form',
                ],
                [
                    'title' => 'Casual engagement',
                    'done' => $signed,
                    'detail' => $signed ? 'The casual pool engagement is signed.' : 'The engagement is not signed yet.',
                    'href' => route('casuals.show', $person).'#contracts',
                    'action' => 'Open the contract',
                ],
                [
                    'title' => 'Day-one pack',
                    'done' => $dayTotal > 0 && $done('day_one') >= $dayTotal,
                    'detail' => $done('day_one').' of '.$dayTotal.' pages acknowledged. The light stays red until these are done.',
                    'href' => route('casuals.pack', $person),
                    'action' => 'Open day-one',
                ],
                [
                    'title' => 'Later induction',
                    'done' => $laterTotal > 0 && $done('later') >= $laterTotal,
                    'detail' => 'Attendance, adjustments, your mentor, and the NESA path. These do not block the first day.',
                    'href' => route('casuals.pack', $person).'#later',
                    'action' => 'Open later pages',
                ],
            ],
        ]);
    }

    private function inviteFromCode(string $code): ?Invite
    {
        if ($code === '') {
            return null;
        }

        return Invite::query()
            ->with('person')
            ->where('token_hash', hash('sha256', $code))
            ->first();
    }

    private function inviteState(?Invite $invite): ?string
    {
        if (! request()->filled('code')) {
            return null;
        }
        if (! $invite) {
            return 'missing';
        }
        if ($invite->used_at) {
            return 'used';
        }
        if ($invite->expires_at->isPast()) {
            return 'expired';
        }

        return 'valid';
    }

    private function ownsInvite(User $user, Invite $invite): bool
    {
        return $user->role === 'casual' && (int) $user->person_id === (int) $invite->person_id;
    }
}
