<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerCheck extends Model
{
    protected $fillable = ['school_id', 'quiz', 'version'];

    protected function casts(): array
    {
        return ['quiz' => 'array'];
    }
}
