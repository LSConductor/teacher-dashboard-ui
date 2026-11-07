<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractTemplate extends Model
{
    protected $fillable = [
        'school_id', 'key', 'name', 'body', 'status', 'version', 'published_at', 'sort',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }
}
