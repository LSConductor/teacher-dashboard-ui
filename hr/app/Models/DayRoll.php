<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DayRoll extends Model
{
    protected $fillable = [
        'school_id', 'person_id', 'on_date',
        'signed_on_at', 'signed_on_by',
        'signed_off_at', 'signed_off_by',
        'closed_at', 'closed_by', 'close_note',
    ];

    protected function casts(): array
    {
        return [
            'on_date' => 'date',
            'signed_on_at' => 'datetime',
            'signed_off_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
