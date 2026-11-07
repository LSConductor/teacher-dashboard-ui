<?php

namespace App\Http\Controllers;

use App\Hr\PeopleCards;
use App\Models\Person;
use App\Models\User;
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

    public function photo(Request $request, User $member)
    {
        abort_unless(PeopleCards::viewerCanSee($request->user(), $member), 404);
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
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
