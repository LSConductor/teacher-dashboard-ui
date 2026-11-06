<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'school_id', 'person_id', 'booked_on', 'class_label', 'plan_url',
        'program_source', 'external_program_id', 'light_at_booking',
        'conductor_note', 'covering_note', 'created_by',
    ];

    protected function casts(): array
    {
        return ['booked_on' => 'date'];
    }
}
