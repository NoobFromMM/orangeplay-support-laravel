<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Services\Support\ConversationService;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $customers = Customer::with(['conversations' => function ($query) {
            $query->latest('last_message_at');
        }, 'messages' => function ($query) {
            $query->latest()->limit(1);
        }])->latest()->get();

        return view('dashboard.index', compact('customers'));
    }

    public function showConversation(string $platform, string $platformUserId)
    {
        $customer = Customer::where('platform', $platform)
            ->where('platform_user_id', $platformUserId)
            ->firstOrFail();

        $messages = Message::where('customer_id', $customer->id)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $conversation = Conversation::where('customer_id', $customer->id)
            ->latest('last_message_at')
            ->first();

        return view('dashboard.conversation', compact('customer', 'messages', 'conversation'));
    }

    public function sendReply(
        string $platform,
        string $platformUserId,
        Request $request,
        ConversationService $conversationService,
        TelegramBotService $botService,
    ): RedirectResponse {
        if ($platform !== 'telegram') {
            return back()->with('error', 'Admin reply currently supports Telegram only.');
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $customer = Customer::where('platform', $platform)
            ->where('platform_user_id', $platformUserId)
            ->firstOrFail();

        $conversation = $conversationService->findOrCreateConversation($customer);

        $sent = $botService->sendMessage($platformUserId, $validated['message']);

        if (! $sent) {
            return back()->with('error', 'Failed to send message via Telegram. Check bot token and connectivity.');
        }

        $conversationService->saveAdminOutboundMessage(
            $conversation,
            $platform,
            $validated['message'],
            'text',
            ['source' => 'dashboard'],
        );

        $conversationService->setStatus($conversation, 'in_chat');

        return back()->with('success', 'Reply sent.');
    }
}
