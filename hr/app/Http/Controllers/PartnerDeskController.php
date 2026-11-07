<?php

namespace App\Http\Controllers;

use App\Hr\Neighbourhood;
use App\Hr\PartnerCourse;
use App\Hr\PartnerPack;
use App\Hr\Roles;
use App\Models\Partner;
use App\Models\PartnerCheck;
use App\Models\PartnerPage;
use App\Models\School;
use App\Models\WwccCheck;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PartnerDeskController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $kind = Roles::kind($user);
        abort_unless(in_array($kind, ['manage', 'neighbourhood'], true), 403);
        $this->prepare($user->school_id);

        $pages = PartnerPage::query()->where('school_id', $user->school_id)->orderBy('sort')->get();
        $check = PartnerCheck::query()->where('school_id', $user->school_id)->firstOrFail();
        $locked = null;
        $query = Partner::query()->where('school_id', $user->school_id)->orderBy('preferred_name');
        if ($kind === 'neighbourhood') {
            if (Neighbourhood::coversAll($user->neighbourhood)) {
                $locked = null;
            } elseif (filled($user->neighbourhood)) {
                $locked = $user->neighbourhood;
                $query->where('neighbourhood', $locked);
            } else {
                $query->whereRaw('0 = 1');
            }
        } elseif ($request->filled('neighbourhood')) {
            $query->where('neighbourhood', $request->string('neighbourhood')->toString());
        }

        $interest = $request->string('interest')->toString();
        $partners = $query->get()->map(function (Partner $partner) use ($pages, $check) {
            $partner->course_status = PartnerCourse::status($partner, $pages, $check);

            return $partner;
        });
        if ($kind === 'neighbourhood') {
            $partners = $partners->where('course_status', 'ready')->values();
        }
        if ($interest !== '' && array_key_exists($interest, PartnerPack::interests())) {
            $partners = $partners->filter(fn (Partner $partner) => in_array($interest, $partner->interests ?? [], true))->values();
        }

        return view('partners.index', [
            'partners' => $partners,
            'locked' => $locked,
            'manages' => $kind === 'manage',
            'interests' => PartnerPack::interests(),
            'neighbourhoods' => Neighbourhood::names($user->school_id),
            'waiting' => $partners->where('course_status', 'waiting')->count(),
        ]);
    }

    public function show(Request $request, Partner $partner)
    {
        $user = $request->user();
        $kind = Roles::kind($user);
        abort_unless((int) $partner->school_id === (int) $user->school_id, 404);
        abort_unless(in_array($kind, ['manage', 'neighbourhood'], true), 403);
        if ($kind === 'neighbourhood' && ! Neighbourhood::coversAll($user->neighbourhood)) {
            abort_unless($partner->neighbourhood === $user->neighbourhood, 403);
        }
        $this->prepare($partner->school_id);
        $pages = PartnerPage::query()->where('school_id', $partner->school_id)->orderBy('sort')->get();
        $check = PartnerCheck::query()->where('school_id', $partner->school_id)->firstOrFail();
        $status = PartnerCourse::status($partner, $pages, $check);
        if ($kind === 'neighbourhood') {
            abort_unless($status === 'ready', 403);
        }

        return view('partners.show', [
            'partner' => $partner,
            'status' => $status,
            'label' => PartnerCourse::label($status),
            'manages' => $kind === 'manage',
            'interests' => PartnerPack::interests(),
        ]);
    }

    public function volunteer(Request $request, Partner $partner)
    {
        $user = $request->user();
        abort_unless(Roles::manages($user), 403);
        abort_unless((int) $partner->school_id === (int) $user->school_id, 404);
        $data = $request->validate([
            'volunteer_status' => ['required', Rule::in(['cleared', 'not_cleared'])],
            'volunteer_number' => ['nullable', 'string', 'max:40'],
            'volunteer_verified_on' => ['nullable', 'date'],
            'volunteer_expires_on' => ['nullable', 'date'],
        ]);
        if ($data['volunteer_status'] === 'cleared') {
            $errors = [];
            if (blank($data['volunteer_number'] ?? null)) {
                $errors['volunteer_number'] = 'A clearance number is required.';
            } elseif (WwccCheck::looksLikeApplicationNumber($data['volunteer_number'])) {
                $errors['volunteer_number'] = 'An APP number is not a clearance.';
            }
            if (blank($data['volunteer_verified_on'] ?? null)) {
                $errors['volunteer_verified_on'] = 'Write the date you verified it.';
            }
            if (blank($data['volunteer_expires_on'] ?? null)) {
                $errors['volunteer_expires_on'] = 'Write the expiry.';
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }
        $partner->fill($data);
        $partner->volunteer_user_id = $user->id;
        $partner->save();
        Audit::record('partner.volunteer', $partner, [
            'name' => $partner->preferred_name,
            'status' => $partner->volunteer_status,
        ]);

        return redirect()->route('partners.show', $partner)->with('status', 'Volunteer check saved.');
    }

    private function prepare(int $schoolId): void
    {
        $school = School::query()->findOrFail($schoolId);
        PartnerPack::ensure($school);
    }
}
