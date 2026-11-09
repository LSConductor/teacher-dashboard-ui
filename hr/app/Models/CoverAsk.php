<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoverAsk extends Model
{
    protected $fillable = [
        'cover_request_id', 'person_id', 'position', 'status', 'token_hash',
        'light_at_ask', 'sent_at', 'responded_at', 'mail_error',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(CoverRequest::class, 'cover_request_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
