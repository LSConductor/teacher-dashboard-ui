<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    protected $fillable = ['name', 'slug', 'timezone', 'incident_desk_url'];

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }
}
