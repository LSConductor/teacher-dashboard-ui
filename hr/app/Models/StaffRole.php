<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffRole extends Model
{
    protected $fillable = ['school_id', 'slug', 'name', 'kind', 'sort'];
}
