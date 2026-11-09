<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InductionModule extends Model
{
    protected $fillable = ['school_id', 'title', 'body', 'phase', 'version', 'sort', 'quiz', 'video_url'];

    protected function casts(): array
    {
        return ['quiz' => 'array'];
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(InductionAcknowledgement::class, 'module_id');
    }
}
