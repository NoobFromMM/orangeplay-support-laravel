<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FaqEntryController;
use App\Http\Controllers\TelegramFileController;
use App\Http\Controllers\Webhooks\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/telegram', TelegramWebhookController::class);

Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/customers/{platform}/{platformUserId}', [DashboardController::class, 'showConversation']);
Route::post('/customers/{platform}/{platformUserId}/reply', [DashboardController::class, 'sendReply']);

Route::get('/dashboard/faqs', [FaqEntryController::class, 'index']);
Route::get('/dashboard/faqs/create', [FaqEntryController::class, 'create']);
Route::post('/dashboard/faqs', [FaqEntryController::class, 'store']);
Route::get('/dashboard/faqs/{faqEntry}/edit', [FaqEntryController::class, 'edit']);
Route::put('/dashboard/faqs/{faqEntry}', [FaqEntryController::class, 'update']);
Route::post('/dashboard/faqs/{faqEntry}/toggle', [FaqEntryController::class, 'toggle']);

Route::get('/telegram/file/{fileId}', [TelegramFileController::class, 'show']);

Route::get('/', function () {
    return view('welcome');
});
