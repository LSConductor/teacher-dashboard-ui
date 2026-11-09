<?php

namespace App\Hr;

use App\Mail\CoverMail;
use App\Models\CoverAsk;
use App\Models\CoverRequest;
use App\Models\Person;
use App\Models\User;
use App\Services\Audit;
use App\Services\ClearToTeach;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CoverDesk
{
    public function __construct(private ClearToTeach $gate, private CoverBoard $board) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $ids
     * @param  array<int, int>  $order
     */
    public function open(User $user, array $data, array $ids, array $order): CoverRequest
    {
        $place = $this->board->place($user, $data['neighbourhood'] ?? null);
        if (Roles::kind($user) !== 'neighbourhood' && ! $place) {
            throw ValidationException::withMessages([
                'neighbourhood' => 'Name the neighbourhood for this class.',
            ]);
        }

        $amber = $user->role === 'conductor' && filled($data['conductor_note'] ?? null);
        if ($user->role !== 'conductor') {
            $data['conductor_note'] = null;
        }
        $chosen = $this->board->chosen($user, $data['on'], $place, $ids, $amber);
        if ($chosen->count() !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'person_ids' => 'Choose people who are free for this day. Red is not asked. Amber needs the Conductor’s note.',
            ]);
        }
        if ($chosen->contains(fn (array $row) => $row['light'] === 'amber') && ! $amber) {
            throw ValidationException::withMessages([
                'conductor_note' => 'An amber casual can be asked only when the Conductor writes a note.',
            ]);
        }

        $ranked = $chosen->sortBy(function (array $row) use ($order) {
            $id = $row['person']->id;

            return [$order[$id] ?? $row['order'] ?? 50, $row['person']->preferred_name];
        })->values();

        $cover = CoverRequest::create([
            'school_id' => $user->school_id,
            'requested_by' => $user->id,
            'neighbourhood' => $place,
            'pool' => 'casual',
            'booked_on' => $data['on'],
            'class_label' => $data['class_label'],
            'teacher_name' => $data['teacher_name'] ?? null,
            'plan_url' => $data['plan_url'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'finish_time' => $data['finish_time'] ?? null,
            'mode' => $data['mode'],
            'status' => 'open',
            'conductor_note' => $data['conductor_note'] ?? null,
        ]);

        $position = 1;
        foreach ($ranked as $row) {
            $ask = $cover->asks()->create([
                'person_id' => $row['person']->id,
                'position' => $position++,
                'status' => 'queued',
                'light_at_ask' => $row['light'],
            ]);
            if ($cover->mode === 'all' || $ask->position === 1) {
                $this->deliver($ask);
            }
        }
        Audit::record('cover.opened', $cover, ['mode' => $cover->mode, 'asked' => $ranked->count()]);

        return $cover->load('asks.person');
    }

    public function accept(CoverAsk $ask): string
    {
        $result = 'moved';
        $notify = collect();

        DB::transaction(function () use ($ask, &$result, &$notify) {
            $cover = CoverRequest::query()->whereKey($ask->cover_request_id)->lockForUpdate()->first();
            $current = CoverAsk::query()->whereKey($ask->id)->lockForUpdate()->first();
            if (! $cover || ! $current || $current->status !== 'sent' || $cover->status !== 'open') {
                $result = $cover && $cover->status === 'filled' ? 'filled' : 'moved';

                return;
            }

            $person = Person::query()->findOrFail($current->person_id);
            $light = $this->gate->assess($person)['light'];
            $booked = $person->bookings()->whereDate('booked_on', $cover->booked_on)->exists();
            if ($booked || $light === 'red' || ($light === 'amber' && blank($cover->conductor_note))) {
                $current->update(['status' => 'passed', 'responded_at' => now()]);
                $result = 'blocked';
                $this->sendNext($cover);

                return;
            }

            $booking = $person->bookings()->create([
                'school_id' => $person->school_id,
                'booked_on' => $cover->booked_on,
                'class_label' => $cover->class_label,
                'teacher_name' => $cover->teacher_name,
                'plan_url' => $cover->plan_url,
                'program_source' => 'wordpress',
                'light_at_booking' => $light,
                'conductor_note' => $light === 'amber' ? $cover->conductor_note : null,
                'created_by' => $cover->requested_by,
            ]);
            Audit::record('booking.created', $booking, ['light' => $light, 'from' => 'cover']);
            ContractDesk::issue($person, 'day-booking', array_filter([
                'class_label' => $cover->class_label,
                'booked_on' => $cover->booked_on->toDateString(),
                'start_time' => $cover->start_time,
                'finish_time' => $cover->finish_time,
            ]));

            $current->update(['status' => 'accepted', 'responded_at' => now()]);
            $cover->update(['status' => 'filled', 'booking_id' => $booking->id]);
            $others = CoverAsk::query()
                ->where('cover_request_id', $cover->id)
                ->where('id', '!=', $current->id)
                ->whereIn('status', ['queued', 'sent'])
                ->get();
            foreach ($others as $other) {
                $wasSent = $other->status === 'sent';
                $other->update(['status' => 'passed', 'responded_at' => now()]);
                if ($wasSent) {
                    $notify->push($other);
                }
            }
            $result = 'booked';
            Audit::record('cover.filled', $cover, ['person' => $person->preferred_name]);
        });

        foreach ($notify as $other) {
            $other->refresh()->load('cover', 'person');
            $this->deliver($other, 'filled', false);
        }

        return $result;
    }

    public function decline(CoverAsk $ask): string
    {
        $result = 'moved';
        DB::transaction(function () use ($ask, &$result) {
            $cover = CoverRequest::query()->whereKey($ask->cover_request_id)->lockForUpdate()->first();
            $current = CoverAsk::query()->whereKey($ask->id)->lockForUpdate()->first();
            if (! $cover || ! $current || $current->status !== 'sent' || $cover->status !== 'open') {
                $result = $cover && $cover->status === 'filled' ? 'filled' : 'moved';

                return;
            }
            $current->update(['status' => 'declined', 'responded_at' => now()]);
            Audit::record('cover.declined', $cover, ['person_id' => $current->person_id]);
            if ($cover->mode === 'ordered') {
                $this->sendNext($cover);
            }
            $result = 'declined';
        });

        return $result;
    }

    public function advance(CoverRequest $cover): void
    {
        abort_unless($cover->status === 'open' && $cover->mode === 'ordered', 422);
        $waiting = $cover->asks()->where('status', 'sent')->get();
        foreach ($waiting as $ask) {
            $ask->update(['status' => 'passed', 'responded_at' => now()]);
            $ask->refresh()->load('cover', 'person');
            $this->deliver($ask, 'passed', false);
        }
        $this->sendNext($cover->fresh());
        Audit::record('cover.advanced', $cover);
    }

    public function resend(CoverAsk $ask): void
    {
        abort_unless($ask->status === 'sent' && $ask->cover->status === 'open', 422);
        $this->deliver($ask);
    }

    public function close(CoverRequest $cover): void
    {
        abort_unless($cover->status === 'open', 422);
        $sent = $cover->asks()->where('status', 'sent')->get();
        $cover->asks()->whereIn('status', ['queued', 'sent'])->update([
            'status' => 'withdrawn',
            'responded_at' => now(),
        ]);
        $cover->update(['status' => 'closed']);
        foreach ($sent as $ask) {
            $ask->refresh()->load('cover', 'person');
            $this->deliver($ask, 'closed', false);
        }
        Audit::record('cover.closed', $cover);
    }

    public function findToken(string $token): ?CoverAsk
    {
        if ($token === '') {
            return null;
        }

        return CoverAsk::query()
            ->with(['cover', 'person'])
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }

    private function sendNext(CoverRequest $cover): void
    {
        $next = $cover->asks()->where('status', 'queued')->orderBy('position')->first();
        if ($next) {
            $this->deliver($next);
        }
    }

    private function deliver(CoverAsk $ask, string $kind = 'ask', bool $freshToken = true): void
    {
        $ask->loadMissing('cover', 'person');
        $url = null;
        if ($kind === 'ask') {
            if ($freshToken || blank($ask->token_hash)) {
                $token = Str::random(40);
                $ask->token_hash = hash('sha256', $token);
            } else {
                $token = null;
            }
            $ask->status = 'sent';
            $ask->sent_at = now();
            $url = $token ? route('cover.reply', ['token' => $token]) : null;
        }
        if ($kind === 'ask' && ! $url) {
            $ask->mail_error = 'The link could not be made again. Send it once more.';
            $ask->save();

            return;
        }

        try {
            Mail::to($ask->person->email)->send(new CoverMail($ask, $kind, $url));
            $ask->mail_error = null;
        } catch (\Throwable $e) {
            $ask->mail_error = 'The email did not send.';
            report($e);
        }
        $ask->save();
    }
}
