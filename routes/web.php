<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentCaseController;
use App\Http\Controllers\TelegramFileController;
use App\Http\Controllers\Webhooks\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/telegram', TelegramWebhookController::class);

Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/customers/{platform}/{platformUserId}', [DashboardController::class, 'showConversation']);
Route::post('/customers/{platform}/{platformUserId}/reply', [DashboardController::class, 'sendReply']);

Route::post('/payments/{paymentCase}/approve', [PaymentCaseController::class, 'approve']);
Route::post('/payments/{paymentCase}/reject', [PaymentCaseController::class, 'reject']);

Route::get('/telegram/file/{fileId}', [TelegramFileController::class, 'show']);

Route::get('/', function () {
    return view('welcome');
});
