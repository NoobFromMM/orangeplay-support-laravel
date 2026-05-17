<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Webhooks\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/telegram', TelegramWebhookController::class);

Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/customers/{platform}/{platformUserId}', [DashboardController::class, 'showConversation']);

Route::get('/', function () {
    return view('welcome');
});
