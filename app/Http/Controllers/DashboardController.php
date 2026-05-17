<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;

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
}
