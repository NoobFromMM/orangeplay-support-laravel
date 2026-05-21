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
        .status-in_progress { background: #fef3c7; color: #d97706; }
        .status-rejected { background: #fef2f2; color: #dc2626; }
        .status-low { background: #f3f4f6; color: #6b7280; }
        .status-normal { background: #ecfeff; color: #0f766e; }
        .status-high { background: #fff1f2; color: #be123c; }
        .pause-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 9999px; font-size: .72rem; font-weight: 600; background: #fff7ed; color: #b45309; border: 1px solid #fed7aa; white-space: nowrap; }

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
        .btn-secondary {
            background: #fff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            padding: 8px 14px;
            font-size: .78rem;
        }
        .btn-secondary:hover { background: #eff6ff; }
        .nav-links { display:flex; gap:16px; align-items:center; font-size:.9rem; }
        .nav-links a { color:#2563eb; text-decoration:none; }
        .nav-links a:hover { text-decoration:underline; }
        .header-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
            flex-shrink: 0;
        }
        .header-action-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .btn-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid #bfdbfe;
            background: #fff;
            color: #1d4ed8;
            font-size: .78rem;
            font-weight: 600;
            text-decoration: none;
        }
        .btn-link:hover { background: #eff6ff; }
        .case-section h2 {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .case-subtitle { font-size: .8rem; color: #6b7280; }
        .case-list { display: flex; flex-direction: column; gap: 10px; }
        .case-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #f9fafb;
            padding: 12px 14px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }
        .case-card.active { background: #eef6ff; border-color: #bfdbfe; }
        .case-card.closed { background: #fafafa; opacity: .92; }
        .case-card-main { min-width: 0; flex: 1; }
        .case-card-title {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            font-size: .92rem;
            font-weight: 700;
            color: #111827;
        }
        .case-code {
            font-size: .72rem;
            font-weight: 700;
            color: #2563eb;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 9999px;
            padding: 2px 8px;
        }
        .case-card-meta { margin-top: 4px; font-size: .78rem; color: #6b7280; }
        .case-card-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
        .timeline-case-card {
            width: min(100%, 560px);
            margin: 0 auto;
            border: 1px solid #dbeafe;
            border-radius: 16px;
            background: #f8fbff;
            padding: 12px 14px;
            box-shadow: 0 1px 1px rgba(15, 23, 42, .05);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .timeline-case-card.case-created { background: #f8fbff; }
        .timeline-case-card.case-updated { background: #fff7ed; border-color: #fed7aa; }
        .timeline-case-head {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .timeline-case-event {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: .72rem;
            font-weight: 700;
            background: #dbeafe;
            color: #1d4ed8;
        }
        .timeline-case-event.case-updated { background: #ffedd5; color: #c2410c; }
        .timeline-case-title {
            font-size: .92rem;
            font-weight: 700;
            color: #111827;
            line-height: 1.4;
            word-break: break-word;
        }
        .timeline-case-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            font-size: .78rem;
            color: #6b7280;
        }
        .timeline-case-link {
            align-self: flex-start;
            font-size: .8rem;
            font-weight: 600;
        }

        /* Telegram-style chat */
        .chat-timeline {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 14px;
            background: #edf2f7;
            border-radius: 14px;
        }

        .chat-row { display: flex; align-items: flex-end; }
        .chat-row-inbound { justify-content: flex-start; }
        .chat-row-outbound { justify-content: flex-end; }
        .chat-row-system { justify-content: center; }

        .chat-bubble {
            display: block;
            width: fit-content;
            min-width: 0;
            max-width: min(75%, 560px);
            padding: 10px 12px 9px;
            border-radius: 18px;
            font-size: .875rem;
            line-height: 1.5;
            text-align: left;
            white-space: normal;
            word-break: normal;
            overflow-wrap: anywhere;
            position: relative;
            box-shadow: 0 1px 1px rgba(15, 23, 42, .06);
        }

        .chat-bubble-inbound {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-bottom-left-radius: 6px;
        }

        .chat-bubble-outbound {
            border-bottom-right-radius: 6px;
            color: #0f172a;
        }

        .chat-bubble-outbound-bot { background: #dbeafe; }
        .chat-bubble-outbound-admin { background: #bfdbfe; }
        .chat-bubble-outbound-system { background: #f3f4f6; color: #4b5563; }

        .chat-system-note {
            max-width: 82%;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            padding: 8px 14px;
            border-radius: 9999px;
            background: #f3f4f6;
            color: #4b5563;
            font-size: .78rem;
            line-height: 1.45;
            text-align: center;
        }

        .chat-message-text {
            text-align: left;
            white-space: pre-wrap;
            word-break: normal;
            overflow-wrap: anywhere;
        }

        .chat-message-text::after {
            content: "";
            display: block;
            clear: both;
        }

        .chat-message-meta-inline {
            float: right;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 12px;
            margin-top: .2rem;
            font-size: .68rem;
            line-height: 1.2;
            color: #64748b;
            white-space: nowrap;
        }

        .chat-sender-dot {
            width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0;
        }

        .chat-sender-customer { background: #3b82f6; }
        .chat-sender-bot { background: #2563eb; }
        .chat-sender-admin { background: #14b8a6; }
        .chat-sender-system { background: #9ca3af; }

        .chat-time { font-size: .68rem; color: #9ca3af; }

        .chat-image { display: block; max-width: 280px; width: 100%; border-radius: 12px; cursor: pointer; transition: opacity .15s; }
        .chat-image:hover { opacity: .9; }
        .chat-image-wrapper { position: relative; }
        .chat-image-link { position: absolute; bottom: 4px; right: 4px; background: rgba(0,0,0,.5); color: #fff; padding: 2px 6px; border-radius: 4px; font-size: .65rem; text-decoration: none; }

        /* Lightbox */
        .lightbox-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,.85);
            z-index: 9999; align-items: center; justify-content: center; flex-direction: column;
        }
        .lightbox-overlay.active { display: flex; }
        .lightbox-img { max-width: 90vw; max-height: 80vh; border-radius: 12px; box-shadow: 0 8px 40px rgba(0,0,0,.3); }
        .lightbox-close { position: absolute; top: 24px; right: 24px; width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,.15); border: none; color: #fff; font-size: 1.5rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .lightbox-close:hover { background: rgba(255,255,255,.25); }
        .lightbox-download { margin-top: 16px; padding: 8px 20px; border-radius: 8px; background: rgba(255,255,255,.15); color: #fff; text-decoration: none; font-size: .8rem; display: none; }
        .text-muted { color: #6b7280; }
        .divider { color: #d1d5db; margin: 0 6px; }
        .order-toggle { display: inline-flex; gap: 2px; background: #f3f4f6; border-radius: 8px; padding: 2px; }
        .order-btn { padding: 4px 10px; border-radius: 6px; font-size: .72rem; font-weight: 600; cursor: pointer; border: none; background: transparent; color: #6b7280; text-decoration: none; }
        .order-btn.active { background: #fff; color: #2563eb; box-shadow: 0 1px 2px rgba(0,0,0,.06); }
        .conversation-started { font-size: .75rem; color: #9ca3af; margin-top: 12px; }
        @media (max-width: 600px) {
            .customer-header { flex-direction: column; }
            .customer-meta-right { align-items: flex-start; }
            .reply-form textarea { min-height: 100px; }
            .card { padding: 16px; }
            .chat-bubble { max-width: 88%; }
            .chat-system-note { max-width: 92%; }
        }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-inner">
            <a href="/dashboard" class="nav-brand">OrangePlay <span>Support</span></a>
            <div class="nav-links">
                <a href="/dashboard">Dashboard</a>
                <a href="/dashboard/faqs">FAQ Manager</a>
                <a href="/cases">Cases</a>
            </div>
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
                    <div class="header-actions">
                        <span class="status-badge status-{{ $statusKey }}">
                            <span class="status-dot status-dot-{{ $statusKey }}"></span>
                            {{ $statusLabel }}
                        </span>
                        @if ($conversation->isBotPaused())
                            <span class="pause-badge">Bot paused</span>
                        @endif
                        <div class="header-action-row">
                            @if ($customer->platform === 'telegram')
                                <a href="/customers/{{ $customer->platform }}/{{ $customer->platform_user_id }}/cases/create" class="btn-link">Create Case</a>
                            @endif
                            @if ($status === 'resolved')
                                <form method="POST" action="/customers/{{ $customer->platform }}/{{ $customer->platform_user_id }}/reopen">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary">Reopen</button>
                                </form>
                            @else
                                <form method="POST" action="/customers/{{ $customer->platform }}/{{ $customer->platform_user_id }}/resolve">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary">Resolve</button>
                                </form>
                            @endif
                        </div>
                        @if ($conversation->created_at)
                            <div class="conversation-started">Started {{ $conversation->created_at->diffForHumans() }}</div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        @if ($conversation && $activeCases->isNotEmpty())
            <div class="card case-section">
                <h2>
                    <span>Active Cases</span>
                    <span class="case-subtitle">{{ $activeCases->count() }} active</span>
                </h2>
                <div class="case-list">
                    @foreach ($activeCases as $case)
                        <div class="case-card active">
                            <div class="case-card-main">
                                <div class="case-card-title">
                                    <span class="case-code">{{ $case->displayCode() }}</span>
                                    <span>{{ \App\Models\SupportCase::labelForCategory($case->category) }}</span>
                                    <span class="status-badge status-{{ $case->status }}">{{ \App\Models\SupportCase::labelForStatus($case->status) }}</span>
                                    <span class="status-badge status-{{ $case->priority }}">{{ \App\Models\SupportCase::labelForPriority($case->priority) }}</span>
                                </div>
                                <div class="case-card-meta">{{ $case->title }} · {{ $case->created_at?->timezone('Asia/Yangon')->format('M j, g:ia') }}</div>
                            </div>
                            <div class="case-card-actions">
                                <a href="/cases/{{ $case->id }}" class="btn-link">View Case</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

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
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
                <h2 style="margin-bottom:0">Timeline</h2>
                <div class="order-toggle">
                    <a href="?order=asc" class="order-btn {{ ($order ?? 'asc') === 'asc' ? 'active' : '' }}">Oldest</a>
                    <a href="?order=desc" class="order-btn {{ ($order ?? 'asc') === 'desc' ? 'active' : '' }}">Newest</a>
                </div>
            </div>
            <div class="chat-timeline">
                @forelse ($timelineItems as $item)
                    @php
                        $itemType = $item['type'];
                        $timestamp = $item['timestamp'];
                        $isMessage = $itemType === 'message';
                        $message = $isMessage ? $item['payload'] : null;
                        $case = ! $isMessage ? $item['payload'] : null;
                        $isInbound = $isMessage && $message->direction === 'inbound';
                        $isSystem = $isMessage && $message->sender_type === 'system';
                        $rowClass = $isInbound ? 'chat-row-inbound' : 'chat-row-outbound';
                        $rowClass = $isSystem ? 'chat-row-system' : $rowClass;
                        $bubbleOutClass = $isMessage ? match ($message->sender_type) {
                            'bot' => 'chat-bubble-outbound-bot',
                            'admin' => 'chat-bubble-outbound-admin',
                            'system' => 'chat-bubble-outbound-system',
                            default => '',
                        } : '';
                        $senderColor = $isMessage ? match ($message->sender_type) {
                            'customer' => 'chat-sender-customer',
                            'bot' => 'chat-sender-bot',
                            'admin' => 'chat-sender-admin',
                            default => 'chat-sender-system',
                        } : 'chat-sender-system';
                        $isImage = $isMessage && $message->message_type === 'image';
                        $telegramFileId = $isMessage ? ($message->metadata['telegram_file_id'] ?? null) : null;
                        $imageCaption = $isMessage ? ($message->metadata['caption'] ?? null) : null;
                        $timelineTimestamp = $timestamp?->timezone('Asia/Yangon')->format('M j, g:ia');
                    @endphp
                    @if ($isMessage)
                        <div class="chat-row {{ $rowClass }}">
                            @if ($isSystem)
                                <div class="chat-system-note">
                                    <span>{{ $message->text ?: 'System message' }}</span>
                                    <span class="chat-time">{{ $timelineTimestamp }}</span>
                                </div>
                            @else
                                <div class="chat-bubble {{ $isInbound ? 'chat-bubble-inbound' : 'chat-bubble-outbound ' . $bubbleOutClass }}">
                                    <div class="chat-message-text">
                                        @if ($isImage)
                                            @if ($telegramFileId)
                                                <img src="/telegram/file/{{ $telegramFileId }}"
                                                     class="chat-image"
                                                     alt="Image attachment"
                                                     loading="lazy"
                                                     onclick="openLightbox('/telegram/file/{{ $telegramFileId }}')">
                                            @else
                                                <div style="padding:20px;text-align:center;background:#f3f4f6;border-radius:8px;color:#9ca3af;font-size:.8rem">
                                                    &#128247; Image attachment
                                                </div>
                                            @endif
                                            @if ($imageCaption)
                                                <div class="chat-caption">{{ $imageCaption }}</div>
                                            @endif
                                        @else
                                            {{ $message->text }}
                                        @endif
                                        <span class="chat-message-meta-inline">
                                            <span class="chat-sender-dot {{ $senderColor }}"></span>
                                            <span>{{ $message->sender_type }}</span>
                                            <span class="chat-time">{{ $timelineTimestamp }}</span>
                                        </span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        @php
                            $eventType = $itemType === 'case_updated' ? 'updated' : 'created';
                            $eventLabel = $itemType === 'case_updated'
                                ? ($case->status === 'rejected' ? 'Case Rejected' : 'Case Resolved')
                                : 'Case Created';
                            $eventTime = $timestamp?->timezone('Asia/Yangon')->format('M j, g:ia');
                        @endphp
                        <div class="chat-row chat-row-system">
                            <div class="timeline-case-card case-{{ $eventType }}">
                                <div class="timeline-case-head">
                                    <span class="case-code">{{ $case->displayCode() }}</span>
                                    <span class="timeline-case-event case-{{ $eventType }}">{{ $eventLabel }}</span>
                                    <span class="status-badge status-{{ $case->status }}">{{ \App\Models\SupportCase::labelForStatus($case->status) }}</span>
                                </div>
                                <div class="timeline-case-title">{{ $case->title }}</div>
                                <div class="timeline-case-meta">
                                    <span>{{ \App\Models\SupportCase::labelForCategory($case->category) }}</span>
                                    <span class="divider">&middot;</span>
                                    <span>{{ $eventTime }}</span>
                                </div>
                                <a href="/cases/{{ $case->id }}" class="timeline-case-link">View Case</a>
                            </div>
                        </div>
                    @endif
                @empty
                    <div style="text-align:center;padding:40px 0" class="text-muted">
                        <div style="font-size:2rem;margin-bottom:8px;opacity:.3">&#128488;</div>
                        <p>No messages yet.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Lightbox --}}
    <div class="lightbox-overlay" id="lightbox" onclick="closeLightbox()">
        <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
        <img class="lightbox-img" id="lightbox-img" src="" alt="Preview" onclick="event.stopPropagation()">
        <a class="lightbox-download" id="lightbox-dl" href="" download onclick="event.stopPropagation()">Download</a>
    </div>

    <script>
        function openLightbox(src) {
            document.getElementById('lightbox-img').src = src;
            document.getElementById('lightbox-dl').href = src;
            document.getElementById('lightbox').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeLightbox() {
            document.getElementById('lightbox').classList.remove('active');
            document.body.style.overflow = '';
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeLightbox();
        });
    </script>
</body>
</html>
