<?php

namespace App\Http\Controllers;

use App\Models\InductionModule;
use App\Models\Person;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SetupController extends Controller
{
    public function index()
    {
        return view('setup.index');
    }

    public function staff(Request $request)
    {
        $staff = User::query()
            ->where('school_id', $request->user()->school_id)
            ->orderBy('name')
            ->get();

        return view('setup.staff', ['staff' => $staff]);
    }

    public function storeStaff(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'role' => ['required', Rule::in(['hr', 'conductor', 'director', 'mentor', 'office'])],
            'neighbourhood' => ['nullable', 'string', 'max:80', 'required_if:role,director'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $member = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'school_id' => $request->user()->school_id,
            'role' => $data['role'],
            'neighbourhood' => $data['neighbourhood'] ?? null,
            'mobile' => $data['mobile'] ?? null,
        ]);
        Audit::record('staff.created', $member, ['role' => $member->role]);

        return redirect()->route('setup.staff')->with('status', $member->name.' can sign in.');
    }

    public function editStaff(Request $request, User $member)
    {
        abort_unless((int) $member->school_id === (int) $request->user()->school_id, 404);

        return view('setup.staff-edit', ['member' => $member]);
    }

    public function updateStaff(Request $request, User $member)
    {
        abort_unless((int) $member->school_id === (int) $request->user()->school_id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($member->id)],
            'role' => ['required', Rule::in(['hr', 'conductor', 'director', 'mentor', 'office', 'casual'])],
            'neighbourhood' => ['nullable', 'string', 'max:80', 'required_if:role,director'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if ((int) $member->id === (int) $request->user()->id && $data['role'] !== $member->role) {
            return back()->withErrors(['role' => 'You cannot change your own role.'])->withInput();
        }
        if ($data['role'] === 'casual' && ! $member->person_id) {
            return back()->withErrors(['role' => 'A casual account is opened with an invite, not from this form.'])->withInput();
        }
        $stillAdmin = in_array($data['role'], ['hr', 'conductor'], true);
        $wasAdmin = in_array($member->role, ['hr', 'conductor'], true);
        $otherAdmin = User::query()
            ->where('school_id', $member->school_id)
            ->where('id', '!=', $member->id)
            ->whereIn('role', ['hr', 'conductor'])
            ->exists();
        if ($wasAdmin && ! $stillAdmin && ! $otherAdmin) {
            return back()->withErrors(['role' => 'The school needs one HR or Conductor account.'])->withInput();
        }

        $member->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'neighbourhood' => $data['neighbourhood'] ?? null,
            'mobile' => $data['mobile'] ?? null,
        ]);
        if (! empty($data['password'])) {
            $member->password = $data['password'];
        }
        $member->save();

        if ($member->person_id) {
            $person = Person::query()->where('school_id', $member->school_id)->find($member->person_id);
            if ($person) {
                $person->update([
                    'email' => $member->email,
                    'neighbourhood' => $member->neighbourhood ?: $person->neighbourhood,
                ]);
            }
        }
        Audit::record('staff.updated', $member, ['role' => $member->role]);

        return redirect()->route('setup.staff')->with('status', $member->name.' is updated.');
    }

    public function induction(Request $request)
    {
        $modules = InductionModule::query()
            ->where('school_id', $request->user()->school_id)
            ->orderByRaw("case phase when 'day_one' then 0 when 'later' then 1 else 2 end")
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return view('setup.induction', ['modules' => $modules]);
    }

    public function storeInduction(Request $request)
    {
        $data = $this->questData($request);
        $sort = (int) InductionModule::query()
            ->where('school_id', $request->user()->school_id)
            ->where('phase', $data['phase'])
            ->max('sort');
        $module = InductionModule::create([
            'school_id' => $request->user()->school_id,
            'title' => $data['title'],
            'body' => $data['body'],
            'phase' => $data['phase'],
            'version' => 1,
            'sort' => $sort + 1,
        ]);
        Audit::record('induction.created', $module, ['phase' => $module->phase]);

        return redirect()->route('setup.induction')->with('status', 'Quest added.');
    }

    public function editInduction(Request $request, InductionModule $module)
    {
        abort_unless((int) $module->school_id === (int) $request->user()->school_id, 404);

        return view('setup.induction-edit', ['module' => $module]);
    }

    public function updateInduction(Request $request, InductionModule $module)
    {
        abort_unless((int) $module->school_id === (int) $request->user()->school_id, 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string'],
        ]);
        $changed = $data['title'] !== $module->title || $data['body'] !== $module->body;
        $acknowledged = $module->acknowledgements()->where('module_version', $module->version)->exists();
        if ($changed && $acknowledged) {
            $data['version'] = $module->version + 1;
        }
        $module->update($data);
        Audit::record('induction.updated', $module, ['version' => $module->version]);
        $status = isset($data['version'])
            ? 'Saved as version '.$module->version.'. Earlier acknowledgements do not cover the new wording.'
            : 'Quest saved.';

        return redirect()->route('setup.induction')->with('status', $status);
    }

    public function moveInduction(Request $request, InductionModule $module)
    {
        abort_unless((int) $module->school_id === (int) $request->user()->school_id, 404);
        $data = $request->validate([
            'direction' => ['nullable', Rule::in(['up', 'down'])],
            'phase' => ['nullable', Rule::in(['day_one', 'later', 'retired'])],
        ]);

        if (! empty($data['phase']) && $data['phase'] !== $module->phase) {
            $sort = (int) InductionModule::query()
                ->where('school_id', $module->school_id)
                ->where('phase', $data['phase'])
                ->max('sort');
            $module->update(['phase' => $data['phase'], 'sort' => $sort + 1]);
            Audit::record('induction.moved', $module, ['phase' => $module->phase]);

            return back()->with('status', 'Quest moved.');
        }

        $direction = $data['direction'] ?? null;
        if (! $direction) {
            return back();
        }
        $siblings = InductionModule::query()
            ->where('school_id', $module->school_id)
            ->where('phase', $module->phase)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
        $index = $siblings->search(fn ($row) => (int) $row->id === (int) $module->id);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if ($index === false || $swap < 0 || $swap >= $siblings->count()) {
            return back();
        }
        $other = $siblings[$swap];
        $first = (int) $module->sort;
        $second = (int) $other->sort;
        if ($first === $second) {
            $first = $index + 1;
            $second = $swap + 1;
        }
        $module->update(['sort' => $second]);
        $other->update(['sort' => $first]);
        Audit::record('induction.moved', $module, ['direction' => $direction]);

        return back();
    }

    private function questData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string'],
            'phase' => ['required', Rule::in(['day_one', 'later'])],
        ]);
    }
}
