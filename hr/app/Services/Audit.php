<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    public static function record(string $action, Model $subject, array $metadata = []): void
    {
        $user = auth()->user();
        AuditLog::create([
            'school_id' => $user?->school_id ?? $subject->school_id ?? $subject->getAttribute('school_id'),
            'actor_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'metadata' => $metadata,
        ]);
    }
}
