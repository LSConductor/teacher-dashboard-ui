<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConductFlag extends Model
{
    protected $fillable = ['school_id', 'person_id', 'kind', 'noted_on', 'location', 'recorded_by'];

    protected function casts(): array
    {
        return ['noted_on' => 'date'];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
