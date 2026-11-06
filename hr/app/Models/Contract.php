<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    protected $fillable = [
        'school_id', 'person_id', 'template_id', 'version', 'merged_body',
        'status', 'sent_at', 'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'template_id');
    }
}
