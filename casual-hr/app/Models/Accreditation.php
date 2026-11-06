<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\AccreditationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'school_id',
    'person_id',
    'nesa_number',
    'level',
    'status',
    'maintenance_start',
    'maintenance_end',
    'casual_evidence_given',
    'fee_year',
    'etams_checked_at',
    'checked_by',
    'alignment_decision',
    'alignment_decided_at',
    'alignment_decided_by',
    'delegate_user_id',
    'other_employer_arrangement',
    'evidence_reference',
    'superseded_at',
])]
class Accreditation extends Model
{
    public const LEVELS = ['conditional', 'provisional', 'proficient', 'highly_accomplished', 'lead'];

    public const STATUSES = ['active', 'leave_of_absence', 'non_practising', 'suspended', 'cancelled'];

    /** @use HasFactory<AccreditationFactory> */
    use BelongsToSchool, HasFactory;

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    protected function casts(): array
    {
        return [
            'maintenance_start' => 'date',
            'maintenance_end' => 'date',
            'casual_evidence_given' => 'boolean',
            'etams_checked_at' => 'datetime',
            'alignment_decided_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }
}
