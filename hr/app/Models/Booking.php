<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = [
        'school_id', 'person_id', 'booked_on', 'class_label', 'teacher_name', 'plan_url',
        'program_source', 'external_program_id', 'light_at_booking',
        'conductor_note', 'covering_note', 'created_by',
    ];

    protected function casts(): array
    {
        return ['booked_on' => 'date'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
