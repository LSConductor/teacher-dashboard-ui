<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdEntry extends Model
{
    protected $fillable = [
        'school_id', 'person_id', 'occurred_on', 'name', 'provider',
        'hours', 'standards', 'activity_type',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'hours' => 'decimal:2',
        ];
    }
}
