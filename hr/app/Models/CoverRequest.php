<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoverRequest extends Model
{
    protected $fillable = [
        'school_id', 'requested_by', 'neighbourhood', 'pool', 'booked_on',
        'class_label', 'teacher_name', 'plan_url', 'start_time', 'finish_time',
        'mode', 'status', 'conductor_note', 'booking_id',
    ];

    protected function casts(): array
    {
        return ['booked_on' => 'date'];
    }

    public function asks(): HasMany
    {
        return $this->hasMany(CoverAsk::class)->orderBy('position');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function when(): string
    {
        return $this->booked_on->timezone('Australia/Sydney')->format('l j F Y');
    }
}
