<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Partner extends Model
{
    protected $fillable = [
        'school_id', 'token', 'preferred_name', 'email', 'mobile', 'relationship',
        'child_name', 'neighbourhood', 'interests', 'strengths', 'work_style',
        'with_children', 'availability', 'seen', 'quiz_version_passed', 'finished_at',
        'volunteer_status', 'volunteer_number', 'volunteer_verified_on',
        'volunteer_expires_on', 'volunteer_user_id',
    ];

    protected function casts(): array
    {
        return [
            'interests' => 'array',
            'seen' => 'array',
            'finished_at' => 'datetime',
            'volunteer_verified_on' => 'date',
            'volunteer_expires_on' => 'date',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
