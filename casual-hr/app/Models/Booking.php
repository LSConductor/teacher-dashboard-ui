<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'school_id',
    'person_id',
    'neighbourhood_id',
    'on_date',
    'class_or_group',
    'director_user_id',
    'wordpress_program_id',
    'light_at_booking',
    'covering_note',
    'conductor_note',
    'conductor_note_by',
    'conductor_note_at',
    'status',
    'booked_by',
    'booked_at',
])]
class Booking extends Model
{
    use BelongsToSchool;

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function neighbourhood(): BelongsTo
    {
        return $this->belongsTo(Neighbourhood::class);
    }

    protected function casts(): array
    {
        return [
            'on_date' => 'date',
            'conductor_note_at' => 'datetime',
            'booked_at' => 'datetime',
        ];
    }
}
