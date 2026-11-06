<?php

namespace App\Http\Controllers;

use App\Hr\ClearToTeach;
use App\Hr\DirectorContactCard;
use App\Models\Person;
use App\Support\Sydney;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CasualListController extends Controller
{
    public function __construct(private readonly ClearToTeach $lights) {}

    public function director(Request $request): View
    {
        $user = $request->user();
        $people = Person::query()
            ->where('employment_status', Person::CASUAL_POOL)
            ->where('neighbourhood_id', $user->neighbourhood_id)
            ->with($this->with())
            ->get();

        return view('casual.index', [
            'title' => 'Call a casual',
            'intro' => 'Your neighbourhood. Green can be booked. Red cannot. Amber needs a Conductor note already on the booking.',
            'groups' => $this->groups($people),
            'today' => Sydney::today()->toDateString(),
        ]);
    }

    public function office(): View
    {
        $people = Person::query()
            ->where('employment_status', Person::CASUAL_POOL)
            ->with($this->with())
            ->get();

        return view('casual.index', [
            'title' => 'Cover for today',
            'intro' => 'Book a green casual. Book amber only when a Conductor note is already on the booking. Red cannot be booked.',
            'groups' => $this->groups($people),
            'today' => Sydney::today()->toDateString(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function with(): array
    {
        return [
            'neighbourhood.director',
            'mentorship.mentor',
            'accreditation',
            'wwcc',
            'bookings' => fn ($query) => $query->whereIn('status', ['booked', 'held']),
        ];
    }

    /**
     * @param  Collection<int, Person>  $people
     * @return Collection<string, Collection<int, DirectorContactCard>>
     */
    private function groups($people)
    {
        $rank = ['green' => 0, 'amber' => 1, 'red' => 2];

        return $people
            ->map(fn (Person $person) => DirectorContactCard::for($person, $this->lights))
            ->sortBy(fn (DirectorContactCard $card) => sprintf('%d-%s', $rank[$card->light] ?? 9, $card->name))
            ->groupBy(fn (DirectorContactCard $card) => $card->neighbourhood);
    }
}
