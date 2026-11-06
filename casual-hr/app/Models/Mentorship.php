<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'school_id',
    'person_id',
    'mentor_person_id',
    'is_accreditation_supervisor',
    'started_on',
    'ended_on',
])]
class Mentorship extends Model
{
    use BelongsToSchool;

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'mentor_person_id');
    }

    protected function casts(): array
    {
        return [
            'is_accreditation_supervisor' => 'boolean',
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }
}
