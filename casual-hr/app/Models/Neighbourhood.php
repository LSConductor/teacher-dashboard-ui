<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\NeighbourhoodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['school_id', 'name'])]
class Neighbourhood extends Model
{
    /** @use HasFactory<NeighbourhoodFactory> */
    use BelongsToSchool, HasFactory;

    public function director(): HasOne
    {
        return $this->hasOne(User::class)->where('role', User::DIRECTOR);
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }
}
