<?php

namespace App\Http\Controllers;

use App\Hr\CoverBoard;
use App\Hr\CoverDesk;
use App\Hr\Neighbourhood;
use App\Hr\Roles;
use App\Models\CoverAsk;
use App\Models\CoverRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CoverController extends Controller
{
    public function index(Request $request, CoverBoard $board)
    {
        $user = $request->user();
        $this->guard($user);
        $on = $this->date($request->string('on')->toString());
        $place = $board->place($user, $request->string('neighbourhood')->toString());
        $locked = Roles::kind($user) === 'neighbourhood' && ! Neighbourhood::coversAll($user->neighbourhood);

        return view('cover.index', [
            'on' => $on,
            'classLabel' => $request->string('class_label')->toString(),
            'teacherName' => $request->string('teacher_name')->toString(),
            'planUrl' => $request->string('plan_url')->toString(),
            'startTime' => $request->string('start_time')->toString() ?: '9:00',
            'finishTime' => $request->string('finish_time')->toString() ?: '3:15',
            'mode' => $request->string('mode')->toString() === 'all' ? 'all' : 'ordered',
            'neighbourhood' => $place,
            'lockedNeighbourhood' => $locked,
            'neighbourhoods' => $locked ? [] : Neighbourhood::names((int) $user->school_id),
            'board' => $board->gather($user, $on, $place),
            'open' => $this->visible($user)->where('status', 'open')->orderBy('booked_on')->get(),
            'filled' => $this->visible($user)->where('status', 'filled')->latest('updated_at')->limit(5)->get(),
            'isConductor' => $user->role === 'conductor',
        ]);
    }

    public function store(Request $request, CoverDesk $desk)
    {
        $user = $request->user();
        $this->guard($user);
        $data = $request->validate([
            'on' => ['required', 'date', 'after_or_equal:today'],
            'class_label' => ['required', 'string', 'max:120'],
            'teacher_name' => ['nullable', 'string', 'max:120'],
            'plan_url' => ['nullable', 'url', 'max:500'],
            'start_time' => ['nullable', 'string', 'max:20'],
            'finish_time' => ['nullable', 'string', 'max:20'],
            'neighbourhood' => ['nullable', 'string', 'max:80'],
            'mode' => ['required', 'in:ordered,all'],
            'person_ids' => ['required', 'array', 'min:1'],
            'person_ids.*' => ['integer'],
            'order' => ['nullable', 'array'],
            'order.*' => ['nullable', 'integer', 'min:1', 'max:99'],
            'conductor_note' => ['nullable', 'string', 'max:2000'],
        ], [
            'class_label.required' => 'Name the class.',
            'person_ids.required' => 'Tick at least one person who is free.',
            'person_ids.min' => 'Tick at least one person who is free.',
        ]);
        $order = [];
        foreach ($data['order'] ?? [] as $id => $value) {
            if ($value) {
                $order[(int) $id] = (int) $value;
            }
        }
        $cover = $desk->open($user, $data, array_map('intval', $data['person_ids']), $order);
        $first = $cover->asks->first(fn (CoverAsk $ask) => $ask->status === 'sent');
        $failed = $cover->asks->contains(fn (CoverAsk $ask) => filled($ask->mail_error));
        if ($failed) {
            $status = 'The ask is saved. The email did not send. Send the link again from this page.';
        } elseif ($cover->mode === 'all') {
            $status = 'Asked '. $cover->asks->count().'. The first yes is the booking.';
        } else {
            $status = 'Asked '.($first->person->preferred_name ?? 'the first person').'. If they say no, the next person is asked.';
        }

        return redirect()->route('cover', ['on' => $cover->booked_on->toDateString()])->with('status', $status);
    }

    public function advance(Request $request, CoverRequest $cover, CoverDesk $desk)
    {
        $this->owns($request->user(), $cover);
        abort_unless($cover->asks()->where('status', 'queued')->exists(), 422);
        $desk->advance($cover);

        return back()->with('status', 'Asked the next person.');
    }

    public function resend(Request $request, CoverAsk $ask, CoverDesk $desk)
    {
        $ask->load('cover');
        $this->owns($request->user(), $ask->cover);
        $desk->resend($ask);
        $ask->refresh();

        return back()->with('status', $ask->mail_error ? 'The email still did not send.' : 'The link was sent again.');
    }

    public function close(Request $request, CoverRequest $cover, CoverDesk $desk)
    {
        $this->owns($request->user(), $cover);
        $desk->close($cover);

        return back()->with('status', 'That ask is closed. Nobody else will be booked from it.');
    }

    public function reply(string $token, CoverDesk $desk)
    {
        $ask = $desk->findToken($token);
        abort_unless($ask, 404);

        return view('cover.reply', ['ask' => $ask, 'token' => $token]);
    }

    public function accept(string $token, CoverDesk $desk)
    {
        $ask = $desk->findToken($token);
        abort_unless($ask, 404);

        return redirect()->route('cover.reply', ['token' => $token])->with('status', $this->words($desk->accept($ask)));
    }

    public function decline(string $token, CoverDesk $desk)
    {
        $ask = $desk->findToken($token);
        abort_unless($ask, 404);

        return redirect()->route('cover.reply', ['token' => $token])->with('status', $this->words($desk->decline($ask)));
    }

    public function acceptOwn(Request $request, CoverAsk $ask, CoverDesk $desk)
    {
        $this->ownAsk($request->user(), $ask);

        return back()->with('status', $this->words($desk->accept($ask)));
    }

    public function declineOwn(Request $request, CoverAsk $ask, CoverDesk $desk)
    {
        $this->ownAsk($request->user(), $ask);

        return back()->with('status', $this->words($desk->decline($ask)));
    }

    private function words(string $result): string
    {
        return match ($result) {
            'booked' => 'You have this day. The office can see the booking.',
            'declined' => 'You declined this day.',
            'filled' => 'This cover is already filled.',
            'blocked' => 'This day can no longer be accepted. The office will ask the next person.',
            default => 'This ask has moved on.',
        };
    }

    private function guard(User $user): void
    {
        abort_unless(in_array(Roles::kind($user), ['manage', 'front_desk', 'neighbourhood'], true), 403);
    }

    private function visible(User $user)
    {
        $query = CoverRequest::query()
            ->where('school_id', $user->school_id)
            ->where('pool', 'casual')
            ->with(['asks.person', 'booking']);
        if (Roles::kind($user) === 'neighbourhood' && ! Neighbourhood::coversAll($user->neighbourhood)) {
            $query->where('neighbourhood', $user->neighbourhood);
        }

        return $query;
    }

    private function owns(User $user, CoverRequest $cover): void
    {
        $this->guard($user);
        abort_unless((int) $cover->school_id === (int) $user->school_id, 404);
        if (Roles::kind($user) === 'neighbourhood' && ! Neighbourhood::coversAll($user->neighbourhood)) {
            abort_unless($cover->neighbourhood === $user->neighbourhood, 404);
        }
    }

    private function ownAsk(User $user, CoverAsk $ask): void
    {
        abort_unless(Roles::isCasual($user) && (int) $user->person_id === (int) $ask->person_id, 403);
        abort_unless($ask->status === 'sent' && $ask->cover->status === 'open', 422);
    }

    private function date(string $on): string
    {
        if ($on !== '') {
            try {
                return Carbon::parse($on)->toDateString();
            } catch (\Throwable) {
                // Fall through to the next school day.
            }
        }
        $day = now()->timezone('Australia/Sydney')->startOfDay();
        if ($day->isWeekend()) {
            $day = $day->nextWeekday();
        }

        return $day->toDateString();
    }
}
