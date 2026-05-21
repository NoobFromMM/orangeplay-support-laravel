<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FaqEntryController;
use App\Http\Controllers\SupportCaseController;
use App\Http\Controllers\TelegramFileController;
use App\Http\Controllers\Webhooks\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/telegram', TelegramWebhookController::class);

Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/customers/{platform}/{platformUserId}', [DashboardController::class, 'showConversation']);
Route::post('/customers/{platform}/{platformUserId}/reply', [DashboardController::class, 'sendReply']);
Route::post('/customers/{platform}/{platformUserId}/resolve', [DashboardController::class, 'resolve']);
Route::post('/customers/{platform}/{platformUserId}/reopen', [DashboardController::class, 'reopen']);
Route::get('/customers/{platform}/{platformUserId}/cases/create', [SupportCaseController::class, 'createForConversation'])->name('customers.cases.create');
Route::post('/customers/{platform}/{platformUserId}/cases', [SupportCaseController::class, 'storeForConversation'])->name('customers.cases.store');

Route::get('/cases', [SupportCaseController::class, 'index'])->name('cases.index');
Route::get('/cases/{supportCase}', [SupportCaseController::class, 'show'])->name('cases.show');
Route::post('/cases/{supportCase}/resolve', [SupportCaseController::class, 'resolve'])->name('cases.resolve');
Route::post('/cases/{supportCase}/reject', [SupportCaseController::class, 'reject'])->name('cases.reject');
Route::get('/messages/{message}/cases/create', [SupportCaseController::class, 'create'])->name('messages.cases.create');
Route::post('/messages/{message}/cases', [SupportCaseController::class, 'store'])->name('messages.cases.store');

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
