<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerPage extends Model
{
    protected $fillable = ['school_id', 'slug', 'title', 'body', 'video_url', 'sort', 'version'];
}
