<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Accreditation extends Model
{
    protected $fillable = [
        'school_id', 'person_id', 'nesa_number', 'level', 'status',
        'maintenance_end', 'fee_year', 'etams_checked_at', 'checked_by',
        'alignment', 'alignment_decided_at', 'alignment_decided_by',
        'casual_evidence_on_file',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_end' => 'date',
            'etams_checked_at' => 'datetime',
            'alignment_decided_at' => 'datetime',
            'casual_evidence_on_file' => 'boolean',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
