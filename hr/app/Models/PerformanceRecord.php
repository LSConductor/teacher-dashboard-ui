<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceRecord extends Model
{
    protected $fillable = [
        'school_id', 'person_id', 'kind', 'recorded_on', 'descriptors', 'body',
        'next_step', 'students_did', 'keep_doing', 'change_next', 'author_id',
    ];

    protected function casts(): array
    {
        return [
            'recorded_on' => 'date',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
