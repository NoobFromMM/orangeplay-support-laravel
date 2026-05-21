<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Customer;
use App\Services\Support\ConversationService;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('filter', 'all');

        $customers = Customer::with(['conversations' => function ($query) use ($filter) {
            $query->latest('last_message_at');
            if ($filter === 'needs_reply') {
                $query->where('status', 'Needs Reply');
            } elseif ($filter === 'resolved') {
                $query->where('status', 'resolved');
            }
        }, 'messages' => function ($query) {
            $query->latest()->limit(1);
        }]);

        if ($filter === 'needs_reply' || $filter === 'resolved') {
            $customers = $customers->whereHas('conversations', function ($q) use ($filter) {
                $q->where('status', $filter === 'needs_reply' ? 'Needs Reply' : 'resolved');
            });
        }

        $customers = $customers->latest()->get();

        return view('dashboard.index', compact('customers', 'filter'));
    }

    public function showConversation(string $platform, string $platformUserId, Request $request)
    {
        $customer = Customer::where('platform', $platform)
            ->where('platform_user_id', $platformUserId)
            ->firstOrFail();

        $conversation = $this->findLatestConversation($customer);

        $order = $request->query('order');
        $order = in_array($order, ['asc', 'desc'], true) ? $order : 'asc';

        $timelineItems = $conversation
            ? $this->buildConversationTimelineItems($conversation, $order)
            : collect();

        $activeCases = collect();

        if ($conversation) {
            $conversation->load(['supportCases' => function ($query) {
                $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
            }]);

            $activeCases = $conversation->supportCases
                ->filter(fn ($case) => $case->isActive())
                ->sort(function ($left, $right): int {
                    $leftTimestamp = $left->created_at?->getTimestamp() ?? 0;
                    $rightTimestamp = $right->created_at?->getTimestamp() ?? 0;

                    if ($leftTimestamp !== $rightTimestamp) {
                        return $rightTimestamp <=> $leftTimestamp;
                    }

                    return $right->id <=> $left->id;
                })
                ->values();
        }

        return view('dashboard.conversation', compact('customer', 'timelineItems', 'conversation', 'activeCases', 'order'));
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

        $conversation->updateWorkflow('Needs Reply', true);

        return back()->with('success', 'Reply sent.');
    }

    public function resolve(
        string $platform,
        string $platformUserId,
        ConversationService $conversationService,
    ): RedirectResponse {
        $conversation = $this->findLatestConversationForPlatformUser($platform, $platformUserId);

        if (! $conversation) {
            return back()->with('error', 'Conversation not found.');
        }

        $conversation->updateWorkflow('resolved', false);

        return back()->with('success', 'Conversation marked resolved.');
    }

    public function reopen(
        string $platform,
        string $platformUserId,
        ConversationService $conversationService,
    ): RedirectResponse {
        $conversation = $this->findLatestConversationForPlatformUser($platform, $platformUserId);

        if (! $conversation) {
            return back()->with('error', 'Conversation not found.');
        }

        $conversation->updateWorkflow('Needs Reply', true);

        return back()->with('success', 'Conversation reopened.');
    }

    protected function findLatestConversation(Customer $customer): ?Conversation
    {
        return Conversation::where('customer_id', $customer->id)
            ->latest('last_message_at')
            ->first();
    }

    protected function findLatestConversationForPlatformUser(string $platform, string $platformUserId): ?Conversation
    {
        $customer = Customer::where('platform', $platform)
            ->where('platform_user_id', $platformUserId)
            ->first();

        if (! $customer) {
            return null;
        }

        return $this->findLatestConversation($customer);
    }

    protected function buildConversationTimelineItems(Conversation $conversation, string $order): Collection
    {
        $messages = $conversation->messages()
            ->get();

        $supportCases = $conversation->supportCases()
            ->get();

        $items = collect();

        foreach ($messages as $message) {
            $items->push([
                'type' => 'message',
                'timestamp' => $message->created_at,
                'id' => $message->id,
                'payload' => $message,
                'sort_type' => $this->timelineTypePriority('message'),
            ]);
        }

        foreach ($supportCases as $case) {
            $items->push([
                'type' => 'case_created',
                'timestamp' => $case->created_at,
                'id' => $case->id,
                'payload' => $case,
                'sort_type' => $this->timelineTypePriority('case_created'),
            ]);

            if (in_array($case->status, ['resolved', 'rejected'], true) && $case->resolved_at) {
                $items->push([
                    'type' => 'case_updated',
                    'timestamp' => $case->resolved_at,
                    'id' => $case->id,
                    'payload' => $case,
                    'sort_type' => $this->timelineTypePriority('case_updated'),
                ]);
            }
        }

        return $items->sort(function (array $left, array $right) use ($order): int {
            $leftTimestamp = $left['timestamp']?->getTimestamp() ?? 0;
            $rightTimestamp = $right['timestamp']?->getTimestamp() ?? 0;

            if ($leftTimestamp !== $rightTimestamp) {
                return $order === 'desc'
                    ? $rightTimestamp <=> $leftTimestamp
                    : $leftTimestamp <=> $rightTimestamp;
            }

            if ($left['sort_type'] !== $right['sort_type']) {
                return $order === 'desc'
                    ? $right['sort_type'] <=> $left['sort_type']
                    : $left['sort_type'] <=> $right['sort_type'];
            }

            return $order === 'desc'
                ? $right['id'] <=> $left['id']
                : $left['id'] <=> $right['id'];
        })->values();
    }

    protected function timelineTypePriority(string $type): int
    {
        return match ($type) {
            'message' => 0,
            'case_created' => 1,
            'case_updated' => 2,
            default => 99,
        };
    }
}
