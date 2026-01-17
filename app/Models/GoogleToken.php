<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleToken extends Model
{
    protected $fillable = [
        'calendar_user_id',
        'access_token',
        'refresh_token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    public function calendarUser(): BelongsTo
    {
        return $this->belongsTo(CalendarUser::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
