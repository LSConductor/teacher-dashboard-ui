<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WwccCheck extends Model
{
    protected $fillable = [
        'school_id', 'person_id', 'wwcc_number', 'name_on_check', 'date_of_birth',
        'outcome', 'verified_at', 'expires_on', 'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'verified_at' => 'datetime',
            'expires_on' => 'date',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public static function looksLikeApplicationNumber(?string $number): bool
    {
        return $number !== null && preg_match('/^APP/i', trim($number)) === 1;
    }
}
