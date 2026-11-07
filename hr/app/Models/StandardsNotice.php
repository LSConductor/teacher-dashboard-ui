<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StandardsNotice extends Model
{
    protected $fillable = [
        'school_id', 'person_id', 'told_on', 'due_on', 'reply_on', 'reply', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'told_on' => 'date',
            'due_on' => 'date',
            'reply_on' => 'date',
        ];
    }
}
