<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\SupportCase;
use App\Services\Support\ConversationService;
use App\Services\Support\SupportCaseService;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Str;

class SupportCaseController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['open', 'resolved', 'rejected'], true)
            ? $request->query('status') : 'all';
        $category = in_array($request->query('category'), SupportCase::categoryOptions(), true)
            ? $request->query('category') : 'all';
        $priority = in_array($request->query('priority'), SupportCase::priorityOptions(), true)
            ? $request->query('priority') : 'all';
        $order = $request->query('order') === 'oldest' ? 'oldest' : 'newest';
        $q = $request->query('q', '');

        $query = SupportCase::with(['customer', 'conversation', 'message']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($category !== 'all') {
            $query->where('category', $category);
        }
        if ($priority !== 'all') {
            $query->where('priority', $priority);
        }

        if (! empty(trim($q))) {
            $searchTerm = trim($q);
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                    ->orWhereHas('customer', function ($cq) use ($searchTerm) {
                        $cq->where('display_name', 'like', "%{$searchTerm}%")
                            ->orWhere('username', 'like', "%{$searchTerm}%")
                            ->orWhere('platform_user_id', 'like', "%{$searchTerm}%");
                    });
            });
        }

        $direction = $order === 'oldest' ? 'asc' : 'desc';
        $query->orderBy('created_at', $direction)->orderBy('id', $direction);

        $cases = $query->get();

        $counts = [
            'all' => SupportCase::count(),
            'open' => SupportCase::where('status', 'open')->count(),
            'resolved' => SupportCase::where('status', 'resolved')->count(),
            'rejected' => SupportCase::where('status', 'rejected')->count(),
        ];

        return view('cases.index', compact('cases', 'status', 'category', 'priority', 'order', 'q', 'counts'));
    }

    public function show(SupportCase $supportCase): View
    {
        $supportCase->load(['customer', 'conversation', 'message']);

        return view('cases.show', [
            'case' => $supportCase,
        ]);
    }

    public function createForConversation(
        string $platform,
        string $platformUserId,
        Request $request,
        SupportCaseService $supportCaseService,
    ): View {
        $customer = $this->findCustomerOrFail($platform, $platformUserId);
        $conversation = $this->findLatestConversationForCustomer($customer);
        abort_unless($conversation, 404);

        $sourceMessages = $supportCaseService->recentSourceMessages($conversation);
        abort_unless($sourceMessages->isNotEmpty(), 404);
        $selectedMessageId = (int) $request->query('message_id', old('message_id', $sourceMessages->first()?->id));
        $selectedMessage = $this->findSelectableSourceMessage($conversation, $selectedMessageId)
            ?? $sourceMessages->first();

        return $this->renderCreateView(
            $customer,
            $conversation,
            $sourceMessages,
            $selectedMessage,
        );
    }

    public function storeForConversation(
        Request $request,
        string $platform,
        string $platformUserId,
        SupportCaseService $supportCaseService,
        ConversationService $conversationService,
        TelegramBotService $botService,
    ): RedirectResponse {
        $customer = $this->findCustomerOrFail($platform, $platformUserId);
        $conversation = $this->findLatestConversationForCustomer($customer);
        abort_unless($conversation, 404);

        $validated = $request->validate([
            'message_id' => ['required', 'integer'],
            'category' => ['required', Rule::in(SupportCase::categoryOptions())],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(SupportCase::priorityOptions())],
            'status' => ['required', Rule::in(SupportCase::statusOptions())],
            'notification' => ['nullable', 'string', 'max:4000'],
        ]);

        if (! $this->findSelectableSourceMessage($conversation, (int) $validated['message_id'])) {
            return back()->withErrors(['message_id' => 'Please select a source message from this conversation.']);
        }

        $case = $supportCaseService->createFromConversationSelection($conversation, (int) $validated['message_id'], $validated);

        // Send customer notification if provided
        $notification = $validated['notification'] ?? null;
        if (! empty(trim((string) $notification))) {
            $messageText = trim((string) $notification);
            $displayCode = $case->displayCode();

            if (str_contains($messageText, '{case_id}')) {
                $messageText = str_replace('{case_id}', $displayCode, $messageText);
            } else {
                $messageText .= "\n\nCase ID: {$displayCode}";
            }

            $chatId = $conversation->customer->platform_user_id;

            if ($customer->platform === 'telegram') {
                $botService->sendMessage($chatId, $messageText);
            }

            $conversationService->saveAdminOutboundMessage(
                $conversation,
                $customer->platform,
                $messageText,
                'text',
                ['source' => 'dashboard', 'event' => 'case_created_notification', 'case_id' => $case->id],
            );
        }

        return redirect()
            ->route('cases.show', $case)
            ->with('success', 'Support case created.');
    }

    public function resolve(
        Request $request,
        SupportCase $supportCase,
        SupportCaseService $supportCaseService,
        ConversationService $conversationService,
        TelegramBotService $botService,
    ): RedirectResponse {
        if (! $supportCase->conversation) {
            return back()->with('error', 'Conversation not found.');
        }

        $validated = $request->validate([
            'resolution_message' => ['required', 'string', 'max:4000'],
        ]);

        if (! $supportCaseService->deliverCustomerUpdate(
            $supportCase,
            'resolved',
            $validated['resolution_message'],
            $conversationService,
            $botService,
        )) {
            return back()->with('error', 'Failed to send case update via Telegram.');
        }

        return back()->with('success', 'Case resolved.');
    }

    public function reject(
        Request $request,
        SupportCase $supportCase,
        SupportCaseService $supportCaseService,
        ConversationService $conversationService,
        TelegramBotService $botService,
    ): RedirectResponse {
        if (! $supportCase->conversation) {
            return back()->with('error', 'Conversation not found.');
        }

        $validated = $request->validate([
            'rejection_message' => ['required', 'string', 'max:4000'],
        ]);

        if (! $supportCaseService->deliverCustomerUpdate(
            $supportCase,
            'rejected',
            $validated['rejection_message'],
            $conversationService,
            $botService,
        )) {
            return back()->with('error', 'Failed to send case update via Telegram.');
        }

        return back()->with('success', 'Case rejected.');
    }

    public function create(Message $message): View|RedirectResponse
    {
        if (! $message->relationLoaded('conversation')) {
            $message->load('conversation.customer');
        }

        if (! $message->conversation || ! $message->conversation->customer) {
            abort(404);
        }

        return redirect()->route('customers.cases.create', [
            'platform' => $message->conversation->customer->platform,
            'platformUserId' => $message->conversation->customer->platform_user_id,
            'message_id' => $message->id,
        ]);
    }

    public function store(Request $request, Message $message, SupportCaseService $supportCaseService): RedirectResponse
    {
        $message->loadMissing('conversation.customer');

        $validated = $request->validate([
            'category' => ['required', Rule::in(SupportCase::categoryOptions())],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(SupportCase::priorityOptions())],
            'status' => ['required', Rule::in(SupportCase::statusOptions())],
        ]);

        $case = $supportCaseService->createFromMessage($message, $validated);

        return redirect()
            ->route('cases.show', $case)
            ->with('success', 'Support case created.');
    }

    protected function renderCreateView(
        Customer $customer,
        Conversation $conversation,
        $sourceMessages,
        ?Message $selectedMessage,
    ): View {
        $selectedMessage ??= $sourceMessages->first();

        $prefilledTitle = $this->prefillTitleFromMessage($selectedMessage);

        return view('cases.create', [
            'customer' => $customer,
            'conversation' => $conversation,
            'sourceMessages' => $sourceMessages,
            'selectedMessage' => $selectedMessage,
            'selectedMessageId' => $selectedMessage?->id,
            'sourceText' => $this->sourceTextForMessage($selectedMessage),
            'prefilledTitle' => $prefilledTitle,
        ]);
    }

    protected function findCustomerOrFail(string $platform, string $platformUserId): Customer
    {
        return Customer::where('platform', $platform)
            ->where('platform_user_id', $platformUserId)
            ->firstOrFail();
    }

    protected function findLatestConversationForCustomer(Customer $customer): ?Conversation
    {
        return Conversation::where('customer_id', $customer->id)
            ->latest('last_message_at')
            ->first();
    }

    protected function findSelectableSourceMessage(Conversation $conversation, int $messageId): ?Message
    {
        if (! $messageId) {
            return null;
        }

        return $conversation->messages()
            ->whereKey($messageId)
            ->where('direction', 'inbound')
            ->where('sender_type', 'customer')
            ->first();
    }

    protected function prefillTitleFromMessage(?Message $message): string
    {
        if (! $message) {
            return 'New support case';
        }

        $sourceText = $this->sourceTextForMessage($message);

        if ($sourceText) {
            return Str::limit($sourceText, 60, '');
        }

        $messageTypeLabel = str_replace('_', ' ', $message->message_type);

        return ucfirst($messageTypeLabel) . ' case';
    }

    protected function sourceTextForMessage(?Message $message): ?string
    {
        if (! $message) {
            return null;
        }

        if ($message->text) {
            return $message->text;
        }

        return $message->metadata['caption'] ?? null;
    }
}
