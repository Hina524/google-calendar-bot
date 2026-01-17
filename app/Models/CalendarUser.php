<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarUser extends Model
{
    protected $fillable = [
        'name',
        'google_email',
        'calendar_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function googleToken(): HasOne
    {
        return $this->hasOne(GoogleToken::class);
    }

    public function watchChannel(): HasOne
    {
        return $this->hasOne(WatchChannel::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }
}
