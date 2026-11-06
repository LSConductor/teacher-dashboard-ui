<?php

namespace App\Models\Concerns;

use App\Models\School;
use App\Tenancy\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope('school', function (Builder $query) {
            $schoolId = app(SchoolContext::class)->id();

            if ($schoolId) {
                $query->where($query->getModel()->getTable().'.school_id', $schoolId);
            }
        });

        static::creating(function (Model $model) {
            if (! $model->getAttribute('school_id') && $schoolId = app(SchoolContext::class)->id()) {
                $model->setAttribute('school_id', $schoolId);
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
