<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatchChannel extends Model
{
    protected $fillable = [
        'calendar_user_id',
        'channel_id',
        'resource_id',
        'expiration',
        'sync_token',
    ];

    protected $casts = [
        'expiration' => 'datetime',
    ];

    public function calendarUser(): BelongsTo
    {
        return $this->belongsTo(CalendarUser::class);
    }

    public function isExpired(): bool
    {
        return $this->expiration->isPast();
    }

    public function isExpiringSoon(): bool
    {
        return $this->expiration->isBefore(now()->addDay());
    }
}
