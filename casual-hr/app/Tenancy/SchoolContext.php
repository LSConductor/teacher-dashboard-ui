<?php

namespace App\Tenancy;

use App\Models\School;

class SchoolContext
{
    private ?School $school = null;

    public function use(?School $school): void
    {
        $this->school = $school;
    }

    public function id(): ?int
    {
        return $this->school?->id;
    }

    public function school(): ?School
    {
        return $this->school;
    }

    public function timezone(): string
    {
        return $this->school?->timezone ?? (string) config('app.timezone', 'Australia/Sydney');
    }
}
