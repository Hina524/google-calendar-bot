<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    protected $fillable = [
        'calendar_user_id',
        'google_event_id',
        'summary',
        'start_time',
        'end_time',
    ];


    public function calendarUser(): BelongsTo
    {
        return $this->belongsTo(CalendarUser::class);
    }
}
