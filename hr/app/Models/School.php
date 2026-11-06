<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    protected $fillable = ['name', 'slug', 'timezone'];

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }
}
