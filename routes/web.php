<?php

use App\Http\Controllers\OAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/oauth/authorize', [OAuthController::class, 'redirect']);
Route::get('/oauth/callback', [OAuthController::class, 'callback']);
