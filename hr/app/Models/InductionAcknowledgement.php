<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InductionAcknowledgement extends Model
{
    protected $fillable = [
        'school_id', 'person_id', 'module_id', 'module_version', 'acknowledged_at',
    ];

    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime'];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(InductionModule::class, 'module_id');
    }
}
