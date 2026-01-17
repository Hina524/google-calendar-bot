<?php

use App\Http\Controllers\CalendarWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/calendar', [CalendarWebhookController::class, 'handle']);
