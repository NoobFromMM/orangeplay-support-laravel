<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Conversation — OrangePlay Support</title>
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f8f9fa; color: #1a1a2e; line-height: 1.6; min-height: 100vh;
        }
        .nav { background: #fff; border-bottom: 1px solid #e9ecef; padding: 0 16px; }
        .nav-inner { max-width: 900px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; height: 56px; }
        .nav-brand { font-size: 1rem; font-weight: 700; color: #1a1a2e; text-decoration: none; letter-spacing: -.02em; }
        .nav-brand span { color: #2563eb; }
        .nav-back { font-size: .85rem; color: #2563eb; text-decoration: none; }
        .nav-back:hover { text-decoration: underline; }
        .container { max-width: 780px; margin: 0 auto; padding: 24px 16px; }

        .card { background: #fff; border-radius: 12px; border: 1px solid #e9ecef; padding: 24px; margin-bottom: 16px; }
        .card h2 { font-size: 1rem; font-weight: 600; margin-bottom: 16px; color: #374151; }

        .customer-header { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 14px; }
        .customer-avatar { width: 48px; height: 48px; border-radius: 12px; background: #eef2ff; color: #4338ca; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; font-weight: 700; flex-shrink: 0; }
        .customer-info { flex: 1; min-width: 0; }
        .customer-info h1 { font-size: 1.2rem; font-weight: 700; line-height: 1.4; word-break: break-word; }
        .customer-info .meta { margin-top: 4px; font-size: .825rem; color: #6b7280; display: flex; flex-wrap: wrap; align-items: center; gap: 4px 10px; }
        .customer-meta-right { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; flex-shrink: 0; }
        .platform-tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: .7rem; font-weight: 600; background: #eef2ff; color: #4338ca; text-transform: capitalize; }
        .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; flex-shrink: 0; }
        .status-dot-resolved { background: #10b981; }
        .status-dot-in_chat { background: #f59e0b; }
        .status-dot-needs_reply { background: #3b82f6; }
        .status-dot-new { background: #9ca3af; }
        .status-badge { display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 9999px; font-size: .75rem; font-weight: 600; white-space: nowrap; }
        .status-resolved { background: #ecfdf5; color: #059669; }
        .status-in_chat { background: #fffbeb; color: #d97706; }
        .status-needs_reply { background: #eff6ff; color: #2563eb; }
        .status-new, .status-open { background: #f9fafb; color: #6b7280; }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: .85rem; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        .reply-form .hint { font-size: .8rem; color: #6b7280; margin-bottom: 10px; display: flex; align-items: center; gap: 4px; }
        .reply-form label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: 6px; color: #374151; }
        .reply-form textarea { width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 14px; font-size: .9rem; font-family: inherit; line-height: 1.65; resize: vertical; min-height: 130px; transition: border-color .15s, box-shadow .15s; }
        .reply-form textarea:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
        .reply-form-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 12px; flex-wrap: wrap; gap: 8px; }
        .char-count { font-size: .75rem; color: #9ca3af; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 24px; border-radius: 8px; font-size: .875rem; font-weight: 600; cursor: pointer; border: none; transition: background .15s, transform .1s; }
        .btn:focus-visible { outline: 3px solid #93c5fd; outline-offset: 1px; }
        .btn:active { transform: scale(.97); }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }

        .timeline { }
        .timeline-item { padding: 20px 0; border-bottom: 1px solid #f3f4f6; position: relative; }
        .timeline-item:last-child { border-bottom: none; padding-bottom: 0; }
        .timeline-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; flex-wrap: wrap; gap: 6px; }
        .sender-badge { display: inline-flex; align-items: center; gap: 5px; padding: 2px 10px; border-radius: 6px; font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; }
        .sender-customer { background: #dbeafe; color: #1e40af; }
        .sender-bot { background: #ede9fe; color: #6b21a8; }
        .sender-admin { background: #d1fae5; color: #065f46; }
        .sender-system { background: #f3f4f6; color: #6b7280; }
        .timeline-time { font-size: .75rem; color: #9ca3af; white-space: nowrap; }
        .message-bubble {
            border-radius: 12px;
            padding: 14px 16px;
            font-size: .875rem;
            line-height: 1.7;
            white-space: pre-wrap;
            word-break: break-word;
            border-left: 3px solid transparent;
        }
        .message-bubble-inbound {
            background: #f9fafb;
            border-top-right-radius: 4px;
            border-left-color: #93c5fd;
        }
        .message-bubble-outbound-bot {
            background: #f5f3ff;
            border-top-left-radius: 4px;
            border-left-color: #c4b5fd;
        }
        .message-bubble-outbound-admin {
            background: #ecfdf5;
            border-top-left-radius: 4px;
            border-left-color: #6ee7b7;
        }
        .text-muted { color: #6b7280; }
        .divider { color: #d1d5db; margin: 0 6px; }
        .conversation-started { font-size: .75rem; color: #9ca3af; margin-top: 12px; }
        .image-placeholder { padding: 40px 20px; text-align: center; background: #f3f4f6; border-radius: 8px; color: #6b7280; font-size: .85rem; }
        .image-preview img { transition: opacity .2s; }
        .image-preview img:hover { opacity: .9; }
        .payment-card { background: #fff; border: 1px solid #e5e7eb; border-left: 3px solid #f59e0b; border-radius: 8px; padding: 14px 16px; margin-top: 4px; }
        .payment-card-header { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; font-size: .85rem; font-weight: 600; color: #374151; }
        .payment-card .badge { display: inline-block; padding: 1px 8px; border-radius: 9999px; font-size: .65rem; font-weight: 600; background: #fef3c7; color: #92400e; }
        .payment-card .fields { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 14px; margin-bottom: 6px; }
        .payment-card .field-label { font-size: .65rem; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: .03em; margin-bottom: 1px; }
        .payment-card .field-value { font-size: .8rem; color: #1f2937; word-break: break-all; font-family: 'SF Mono', 'Menlo', 'Consolas', monospace; }
        .payment-card .hint { font-size: .7rem; color: #9ca3af; margin-top: 6px; padding-top: 6px; border-top: 1px solid #f3f4f6; }
        @media (max-width: 600px) {
            .customer-header { flex-direction: column; }
            .customer-meta-right { align-items: flex-start; }
            .reply-form textarea { min-height: 100px; }
            .card { padding: 16px; }
        }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-inner">
            <a href="/dashboard" class="nav-brand">OrangePlay <span>Support</span></a>
            <a href="/dashboard" class="nav-back">&larr; Dashboard</a>
        </div>
    </nav>

    <div class="container">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- Customer Header --}}
        <div class="card">
            <div class="customer-header">
                <div class="customer-avatar">{{ mb_substr($customer->display_name ?? $customer->platform_user_id, 0, 2) }}</div>
                <div class="customer-info">
                    <h1>{{ $customer->display_name ?? $customer->platform_user_id }}</h1>
                    <div class="meta">
                        @if ($customer->username)
                            <span>&#64;{{ $customer->username }}</span>
                            <span class="divider">&middot;</span>
                        @endif
                        <span class="platform-tag">{{ $customer->platform }}</span>
                        <span class="divider">&middot;</span>
                        <span>ID {{ $customer->platform_user_id }}</span>
                    </div>
                </div>
                @if ($conversation)
                    @php
                        $status = $conversation->status;
                        $statusLabel = match($status) {
                            'resolved' => 'Resolved',
                            'in_chat' => 'In Chat',
                            'Needs Reply' => 'Needs Reply',
                            default => $status,
                        };
                        $statusKey = match($status) {
                            'resolved' => 'resolved',
                            'in_chat' => 'in_chat',
                            'Needs Reply' => 'needs_reply',
                            default => 'new',
                        };
                    @endphp
                    <div class="customer-meta-right">
                        <span class="status-badge status-{{ $statusKey }}">
                            <span class="status-dot status-dot-{{ $statusKey }}"></span>
                            {{ $statusLabel }}
                        </span>
                        @if ($conversation->created_at)
                            <div class="conversation-started">Started {{ $conversation->created_at->diffForHumans() }}</div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Reply Form --}}
        @if ($customer->platform === 'telegram')
        <div class="card reply-form">
            <h2>Reply to Customer</h2>
            <div class="hint">&#9993; Reply will be sent to the customer via Telegram.</div>
            <form method="POST" action="/customers/{{ $customer->platform }}/{{ $customer->platform_user_id }}/reply">
                @csrf
                <label for="reply-text">Your message</label>
                <textarea id="reply-text" name="message" rows="4" maxlength="4000"
                    placeholder="Type your reply here..." required>{{ old('message') }}</textarea>
                <div class="reply-form-footer">
                    <span class="char-count">Max 4000 characters</span>
                    <button type="submit" class="btn btn-primary">
                        &#8593; Send Reply
                    </button>
                </div>
            </form>
        </div>
        @endif

        {{-- Timeline --}}
        <div class="card">
            <h2>Timeline</h2>
            <div class="timeline">
                @forelse ($messages as $message)
                    @php
                        $senderLabel = match($message->sender_type) {
                            'customer' => 'Customer',
                            'bot' => 'Bot',
                            'admin' => 'Admin',
                            default => ucfirst($message->sender_type),
                        };
                        $bubbleClass = match($message->direction . '-' . $message->sender_type) {
                            'inbound-customer' => 'message-bubble-inbound',
                            'outbound-bot' => 'message-bubble-outbound-bot',
                            'outbound-admin' => 'message-bubble-outbound-admin',
                            default => 'message-bubble-inbound',
                        };
                        $isImage = $message->message_type === 'image';
                        $isPaymentReview = $message->message_type === 'payment_review_card';
                        $telegramFileId = $message->metadata['telegram_file_id'] ?? null;
                        $imageCaption = $message->metadata['caption'] ?? null;
                    @endphp
                    <div class="timeline-item">
                        <div class="timeline-header">
                            <span class="sender-badge sender-{{ $message->sender_type }}">{{ $senderLabel }}</span>
                            <span class="timeline-time">{{ $message->created_at->format('M j, Y \a\t g:ia') }}</span>
                        </div>
                        @if ($isPaymentReview)
                            @php
                                $pcMeta = $message->metadata;
                                $cardProvider = $pcMeta['provider'] ?? '-';
                                $cardTxnId = $pcMeta['transaction_id'] ?? '-';
                                $cardAmount = $pcMeta['amount'] ? number_format($pcMeta['amount']) . ' MMK' : '-';
                                $cardCaseId = $pcMeta['payment_case_id'] ?? null;
                                $cardEmail = $pcMeta['customer_email'] ?? null;
                            @endphp
                            <div class="payment-card">
                                <div class="payment-card-header">
                                    &#128179; Payment Review
                                    <span class="badge">Pending Review</span>
                                </div>
                                <div class="fields">
                                    <div class="field">
                                        <div class="field-label">Provider</div>
                                        <div class="field-value">{{ $cardProvider }}</div>
                                    </div>
                                    <div class="field">
                                        <div class="field-label">Transaction ID</div>
                                        <div class="field-value">{{ $cardTxnId }}</div>
                                    </div>
                                    <div class="field">
                                        <div class="field-label">Amount</div>
                                        <div class="field-value">{{ $cardAmount }}</div>
                                    </div>
                                    <div class="field">
                                        <div class="field-label">Case ID</div>
                                        <div class="field-value">{{ $cardCaseId ?? '-' }}</div>
                                    </div>
                                    <div class="field">
                                        <div class="field-label">Email</div>
                                        <div class="field-value">{{ $cardEmail ?? '-' }}</div>
                                    </div>
                                </div>
                                @php
                                    $matchedCase = $cardCaseId ? $paymentCases->firstWhere('id', $cardCaseId) : null;
                                    $caseStatus = $matchedCase?->status;
                                @endphp
                                <div class="hint" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                                    <span>
                                        @if ($caseStatus === 'pending_review')
                                            Payment screenshot detected. Waiting for admin review.
                                        @elseif ($caseStatus === 'needs_email')
                                            Waiting for customer email response.
                                        @elseif ($caseStatus === 'approved')
                                            Payment has been approved.
                                        @elseif ($caseStatus === 'rejected')
                                            Payment has been rejected.
                                        @else
                                            Payment review in progress.
                                        @endif
                                    </span>
                                    @if ($caseStatus === 'pending_review' && $matchedCase)
                                        <span style="display:flex;gap:6px">
                                            <form method="POST" action="/payments/{{ $matchedCase->id }}/approve" style="display:inline">
                                                @csrf
                                                <button type="submit" style="padding:4px 14px;border-radius:6px;border:1px solid #059669;background:#ecfdf5;color:#059669;font-size:.75rem;font-weight:600;cursor:pointer">Approve</button>
                                            </form>
                                            <form method="POST" action="/payments/{{ $matchedCase->id }}/reject" style="display:inline">
                                                @csrf
                                                <button type="submit" style="padding:4px 14px;border-radius:6px;border:1px solid #dc2626;background:#fef2f2;color:#dc2626;font-size:.75rem;font-weight:600;cursor:pointer">Reject</button>
                                            </form>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @else
                        <div class="message-bubble {{ $bubbleClass }}">
                            @if ($isImage)
                                <div class="image-preview">
                                    @if ($telegramFileId)
                                        <a href="/telegram/file/{{ $telegramFileId }}" target="_blank" rel="noopener">
                                            <img src="/telegram/file/{{ $telegramFileId }}"
                                                 alt="Image attachment"
                                                 loading="lazy"
                                                 style="max-width:100%;max-height:400px;border-radius:8px;display:block;">
                                        </a>
                                    @else
                                        <div class="image-placeholder">
                                            &#128247; Image attachment
                                        </div>
                                    @endif
                                </div>
                                @if ($imageCaption)
                                    <div style="margin-top:8px;font-size:.85rem;color:#4b5563;">{{ $imageCaption }}</div>
                                @endif
                            @else
                                {{ $message->text }}
                            @endif
                        </div>
                        @endif
                    </div>
                @empty
                    <div style="text-align:center;padding:40px 0" class="text-muted">
                        <div style="font-size:2rem;margin-bottom:8px;opacity:.3">&#128488;</div>
                        <p>No messages yet.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</body>
</html>
