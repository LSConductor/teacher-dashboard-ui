<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DayNote extends Model
{
    protected $fillable = [
        'school_id', 'person_id', 'booking_id', 'author_id', 'body', 'descriptors',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
