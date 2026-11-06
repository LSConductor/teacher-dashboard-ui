<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['school_id', 'person_id', 'token_hash', 'expires_at', 'used_at', 'created_by'])]
class Invite extends Model
{
    use BelongsToSchool;

    public static function hashToken(string $raw): string
    {
        return hash('sha256', $raw);
    }

    public static function issue(Person $person, User $actor): string
    {
        $raw = Str::random(48);

        static::create([
            'school_id' => $person->school_id,
            'person_id' => $person->id,
            'token_hash' => self::hashToken($raw),
            'expires_at' => now()->addDays(7),
            'created_by' => $actor->id,
        ]);

        return $raw;
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function isSpent(): bool
    {
        return $this->used_at !== null || $this->expires_at->lte(now());
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
