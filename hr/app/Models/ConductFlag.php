<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConductFlag extends Model
{
    protected $fillable = ['school_id', 'person_id', 'kind', 'noted_on', 'location', 'recorded_by'];

    protected function casts(): array
    {
        return ['noted_on' => 'date'];
    }
}
