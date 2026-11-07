<?php

namespace App\Http\Controllers;

use App\Hr\PeopleCards;
use App\Hr\StaffPhoto;
use App\Models\Person;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PeopleController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $managing = in_array($user->role, ['hr', 'conductor'], true);
        $person = null;
        if ($user->role === 'casual' && $user->person_id) {
            $person = Person::query()
                ->where('school_id', $user->school_id)
                ->with('bookings')
                ->find($user->person_id);
        }

        if ($person) {
            $cards = PeopleCards::forPerson($person);
            $hidden = collect();
        } elseif ($user->role === 'director') {
            $cards = PeopleCards::forNeighbourhood((int) $user->school_id, $user->neighbourhood);
            $hidden = collect();
        } else {
            $cards = PeopleCards::schoolwide((int) $user->school_id);
            $hidden = $managing ? PeopleCards::hidden((int) $user->school_id, $cards) : collect();
        }

        return view('people.index', [
            'cards' => $cards,
            'hidden' => $hidden,
            'visits' => PeopleCards::visits($person),
            'managing' => $managing,
            'person' => $person,
        ]);
    }

    public function order(Request $request)
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ]);
        $ids = array_values(array_unique(array_map('intval', $data['order'])));
        $members = User::query()
            ->where('school_id', $request->user()->school_id)
            ->whereIn('id', $ids)
            ->where('role', '!=', 'casual')
            ->get()
            ->keyBy('id');
        $position = 1;
        $first = null;
        foreach ($ids as $id) {
            $member = $members->get($id);
            if (! $member) {
                continue;
            }
            $member->people_sort = $position;
            $member->save();
            $first ??= $member;
            $position++;
        }
        if ($first) {
            Audit::record('people.ordered', $first, ['order' => $ids]);
        }

        return redirect()->route('people')->with('status', 'The people order is saved.');
    }

    public function photo(Request $request, User $member)
    {
        abort_unless(PeopleCards::viewerCanSee($request->user(), $member), 404);
        StaffPhoto::ensureSized($member);
        $member->refresh();
        abort_unless(filled($member->photo_path) && Storage::disk('local')->exists($member->photo_path), 404);

        $extension = strtolower(pathinfo($member->photo_path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };

        return response()->file(Storage::disk('local')->path($member->photo_path), [
            'Content-Type' => $mime,
        ])->setPrivate();
    }
}
