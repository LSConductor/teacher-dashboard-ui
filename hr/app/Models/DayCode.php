<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DayCode extends Model
{
    protected $fillable = ['school_id', 'on_date', 'code'];

    protected function casts(): array
    {
        return ['on_date' => 'date'];
    }
}
