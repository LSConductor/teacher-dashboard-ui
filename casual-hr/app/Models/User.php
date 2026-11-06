<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['school_id', 'name', 'email', 'password', 'role', 'neighbourhood_id', 'google_subject'])]
#[Hidden(['password', 'remember_token', 'google_subject'])]
class User extends Authenticatable
{
    public const CONDUCTOR = 'conductor';

    public const HR = 'hr';

    public const DIRECTOR = 'director';

    public const OFFICE = 'office';

    /** @use HasFactory<UserFactory> */
    use BelongsToSchool, HasFactory, Notifiable;

    public function neighbourhood(): BelongsTo
    {
        return $this->belongsTo(Neighbourhood::class);
    }

    public function isHr(): bool
    {
        return $this->role === self::HR;
    }

    public function isConductor(): bool
    {
        return $this->role === self::CONDUCTOR;
    }

    public function isDirector(): bool
    {
        return $this->role === self::DIRECTOR;
    }

    public function isOffice(): bool
    {
        return $this->role === self::OFFICE;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
