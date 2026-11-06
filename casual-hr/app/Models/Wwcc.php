<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\WwccFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'school_id',
    'person_id',
    'wwcc_number',
    'name_used',
    'date_of_birth',
    'outcome',
    'verified_at',
    'expires_on',
    'verified_by',
    'app_number_blocked',
    'bar_blocked',
    'interim_bar_blocked',
    'expired_blocked',
    'superseded_at',
])]
#[Hidden(['wwcc_number', 'date_of_birth', 'name_used'])]
class Wwcc extends Model
{
    public const OUTCOMES = ['pending', 'verified', 'not_verified', 'barred', 'interim_bar', 'expired', 'app_number'];

    /** @use HasFactory<WwccFactory> */
    use BelongsToSchool, HasFactory;

    protected $table = 'working_with_children_checks';

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'verified_at' => 'datetime',
            'expires_on' => 'date',
            'app_number_blocked' => 'boolean',
            'bar_blocked' => 'boolean',
            'interim_bar_blocked' => 'boolean',
            'expired_blocked' => 'boolean',
            'superseded_at' => 'datetime',
        ];
    }
}
