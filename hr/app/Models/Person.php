<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Person extends Model
{
    protected $fillable = [
        'school_id', 'legal_name', 'preferred_name', 'email', 'mobile',
        'postal_address', 'emergency_contact_name', 'emergency_contact_mobile',
        'neighbourhood', 'stages', 'status', 'right_to_work', 'heard_about',
        'adjustments', 'payroll_declaration_lodged', 'blocked', 'block_reason',
        'mentor_user_id', 'available_days', 'earliest_start', 'neighbourhoods',
    ];

    protected function casts(): array
    {
        return [
            'stages' => 'array',
            'available_days' => 'array',
            'neighbourhoods' => 'array',
            'payroll_declaration_lodged' => 'boolean',
            'blocked' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_user_id');
    }

    public function accreditation(): HasOne
    {
        return $this->hasOne(Accreditation::class);
    }

    public function wwcc(): HasOne
    {
        return $this->hasOne(WwccCheck::class);
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(InductionAcknowledgement::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function unavailabilities(): HasMany
    {
        return $this->hasMany(Unavailability::class);
    }

    public function dayNotes(): HasMany
    {
        return $this->hasMany(DayNote::class);
    }

    public function pdEntries(): HasMany
    {
        return $this->hasMany(PdEntry::class);
    }
}
