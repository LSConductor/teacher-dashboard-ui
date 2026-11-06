<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Support\Sydney;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

#[Fillable([
    'school_id',
    'legal_name',
    'preferred_name',
    'email',
    'mobile',
    'postal_address',
    'emergency_contact_name',
    'emergency_contact_mobile',
    'neighbourhood_id',
    'stages',
    'employment_status',
    'google_subject',
    'payroll_reference',
    'engagement_signed_at',
    'day_one_induction_completed_at',
    'later_induction_window_ends_on',
    'pd_conversation_due_on',
])]
#[Hidden(['postal_address', 'payroll_reference', 'google_subject', 'emergency_contact_name', 'emergency_contact_mobile'])]
class Person extends Model
{
    public const APPLICANT = 'applicant';

    public const CASUAL_POOL = 'casual_pool';

    public const ENDED = 'ended';

    public const STAGES = ['ES1', 'S1', 'S2', 'S3', 'S4', 'S5', 'S6'];

    /** @use HasFactory<PersonFactory> */
    use BelongsToSchool, HasFactory;

    public function neighbourhood(): BelongsTo
    {
        return $this->belongsTo(Neighbourhood::class);
    }

    public function accreditation(): HasOne
    {
        return $this->hasOne(Accreditation::class)->whereNull('superseded_at');
    }

    public function accreditations(): HasMany
    {
        return $this->hasMany(Accreditation::class);
    }

    public function wwcc(): HasOne
    {
        return $this->hasOne(Wwcc::class)->whereNull('superseded_at');
    }

    public function wwccs(): HasMany
    {
        return $this->hasMany(Wwcc::class);
    }

    public function mentorship(): HasOne
    {
        return $this->hasOne(Mentorship::class)->whereNull('ended_on');
    }

    public function mentorships(): HasMany
    {
        return $this->hasMany(Mentorship::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(Invite::class);
    }

    public function syncMentor(?int $mentorId, bool $supervisor): void
    {
        if ($mentorId === $this->id) {
            throw new InvalidArgumentException('A person cannot mentor themselves.');
        }

        $current = $this->mentorship;

        if ($mentorId === null) {
            $current?->update(['ended_on' => Sydney::today()->toDateString()]);
            $this->unsetRelation('mentorship');

            return;
        }

        if ($current && (int) $current->mentor_person_id === $mentorId) {
            $current->update(['is_accreditation_supervisor' => $supervisor]);

            return;
        }

        $current?->update(['ended_on' => Sydney::today()->toDateString()]);

        $this->mentorships()->create([
            'school_id' => $this->school_id,
            'mentor_person_id' => $mentorId,
            'is_accreditation_supervisor' => $supervisor,
            'started_on' => Sydney::today()->toDateString(),
        ]);

        $this->unsetRelation('mentorship');
    }

    protected function casts(): array
    {
        return [
            'stages' => 'array',
            'engagement_signed_at' => 'datetime',
            'day_one_induction_completed_at' => 'datetime',
            'later_induction_window_ends_on' => 'date',
            'pd_conversation_due_on' => 'date',
        ];
    }
}
