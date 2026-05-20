<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\SupportCase;
use App\Services\Support\SupportCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class SupportCaseController extends Controller
{
    public function index(): View
    {
        $cases = SupportCase::with(['customer', 'conversation', 'message'])
            ->latest('created_at')
            ->get();

        return view('cases.index', compact('cases'));
    }

    public function show(SupportCase $supportCase): View
    {
        $supportCase->load(['customer', 'conversation', 'message']);

        return view('cases.show', [
            'case' => $supportCase,
        ]);
    }

    public function create(Message $message): View
    {
        $this->authorizeMessage($message);

        $message->loadMissing('customer', 'conversation');

        $sourceText = $message->text ?: ($message->metadata['caption'] ?? null);
        $prefilledTitle = $sourceText
            ? \Illuminate\Support\Str::limit($sourceText, 60, '')
            : ucfirst(str_replace('_', ' ', $message->message_type)) . ' case';

        return view('cases.create', [
            'message' => $message,
            'sourceText' => $sourceText,
            'prefilledTitle' => $prefilledTitle,
        ]);
    }

    public function store(Request $request, Message $message, SupportCaseService $supportCaseService): RedirectResponse
    {
        $this->authorizeMessage($message);

        $validated = $request->validate([
            'category' => ['required', Rule::in(SupportCase::categoryOptions())],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(SupportCase::priorityOptions())],
            'admin_note' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(SupportCase::statusOptions())],
        ]);

        $case = $supportCaseService->createFromMessage($message, $validated);

        return redirect()
            ->route('cases.show', $case)
            ->with('success', 'Support case created.');
    }

    protected function authorizeMessage(Message $message): void
    {
        abort_unless($message->direction === 'inbound' && $message->sender_type === 'customer', 403);
    }
}
