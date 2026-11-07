<?php

namespace App\Http\Controllers;

use App\Hr\Roles;
use App\Models\StaffRole;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        Roles::ensure($schoolId);
        $roles = Roles::choices($schoolId, true);
        $counts = User::query()
            ->where('school_id', $schoolId)
            ->selectRaw('role, count(*) as people')
            ->groupBy('role')
            ->pluck('people', 'role');

        return view('setup.roles', [
            'roles' => $roles,
            'counts' => $counts,
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'kind' => ['required', Rule::in(array_keys(Roles::KINDS))],
        ]);
        $slug = Roles::slugFromName($schoolId, $data['name']);
        if (! $slug) {
            return back()->withErrors(['name' => 'Use a different name. That role is already there, or the name cannot be saved.'])->withInput();
        }
        $sort = (int) StaffRole::query()->where('school_id', $schoolId)->max('sort');
        $role = StaffRole::query()->create([
            'school_id' => $schoolId,
            'slug' => $slug,
            'name' => trim($data['name']),
            'kind' => $data['kind'],
            'sort' => $sort + 1,
        ]);
        Roles::forget($schoolId);
        Audit::record('role.created', $role, ['name' => $role->name, 'kind' => $role->kind]);

        return redirect()->route('setup.roles')->with('status', $role->name.' is a role you can give to staff.');
    }

    public function edit(Request $request, StaffRole $role)
    {
        abort_unless((int) $role->school_id === (int) $request->user()->school_id, 404);
        $people = User::query()
            ->where('school_id', $role->school_id)
            ->where('role', $role->slug)
            ->count();

        return view('setup.role-edit', [
            'role' => $role,
            'people' => $people,
        ]);
    }

    public function update(Request $request, StaffRole $role)
    {
        $schoolId = (int) $request->user()->school_id;
        abort_unless((int) $role->school_id === $schoolId, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'kind' => ['nullable', Rule::in(array_keys(Roles::KINDS))],
        ]);
        $name = trim($data['name']);
        $taken = StaffRole::query()
            ->where('school_id', $schoolId)
            ->where('id', '!=', $role->id)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->exists();
        if ($taken) {
            return back()->withErrors(['name' => 'Another role already uses that name.'])->withInput();
        }

        if ($role->kind === 'casual') {
            $role->name = $name;
            $role->save();
            Roles::forget($schoolId);
            Audit::record('role.updated', $role, ['name' => $role->name]);

            return redirect()->route('setup.roles')->with('status', $role->name.' is updated.');
        }

        $kind = $data['kind'] ?? $role->kind;
        if ($role->kind === 'manage' && $kind !== 'manage' && ! $this->anotherManager($schoolId, $role)) {
            return back()->withErrors(['kind' => 'The school needs one role that can manage setup.'])->withInput();
        }
        $role->name = $name;
        $role->kind = $kind;
        $role->save();
        Roles::forget($schoolId);
        Audit::record('role.updated', $role, ['name' => $role->name, 'kind' => $role->kind]);

        return redirect()->route('setup.roles')->with('status', $role->name.' is updated.');
    }

    public function destroy(Request $request, StaffRole $role)
    {
        $schoolId = (int) $request->user()->school_id;
        abort_unless((int) $role->school_id === $schoolId, 404);
        if ($role->kind === 'casual') {
            return back()->withErrors(['role' => 'The casual role stays. A casual joins from an invite.']);
        }
        $used = User::query()->where('school_id', $schoolId)->where('role', $role->slug)->exists();
        if ($used) {
            return back()->withErrors(['role' => 'Move people off this role before you remove it.']);
        }
        if ($role->kind === 'manage' && ! $this->anotherManager($schoolId, $role)) {
            return back()->withErrors(['role' => 'The school needs one role that can manage setup.']);
        }
        Audit::record('role.removed', $role, ['name' => $role->name]);
        $role->delete();
        Roles::forget($schoolId);

        return redirect()->route('setup.roles')->with('status', 'That role is removed.');
    }

    private function anotherManager(int $schoolId, StaffRole $role): bool
    {
        $slugs = StaffRole::query()
            ->where('school_id', $schoolId)
            ->where('kind', 'manage')
            ->where('id', '!=', $role->id)
            ->pluck('slug');

        return User::query()
            ->where('school_id', $schoolId)
            ->whereIn('role', $slugs)
            ->exists();
    }
}
