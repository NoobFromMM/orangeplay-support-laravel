<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Conversation — OrangePlay Support</title>
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; color: #1f2937; line-height: 1.5; }
        .container { max-width: 720px; margin: 0 auto; padding: 24px 16px; }
        .back-link { display: inline-block; margin-bottom: 16px; font-size: .875rem; color: #2563eb; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); padding: 20px; margin-bottom: 16px; }
        .card h2 { font-size: 1.125rem; font-weight: 600; margin-bottom: 16px; }
        .customer-header h1 { font-size: 1.25rem; font-weight: 700; margin-bottom: 4px; }
        .customer-header .meta { font-size: .875rem; color: #6b7280; }
        .platform-tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: .75rem; background: #e0e7ff; color: #3730a3; }
        .status-badge { display: inline-block; margin-top: 8px; padding: 3px 12px; border-radius: 9999px; font-size: .75rem; font-weight: 600; }
        .status-resolved { background: #d1fae5; color: #065f46; }
        .status-in_chat { background: #fef3c7; color: #92400e; }
        .status-needs_reply { background: #dbeafe; color: #1e40af; }
        .status-new, .status-open { background: #dbeafe; color: #1e40af; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: .875rem; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .reply-form textarea { width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 12px; font-size: .875rem; font-family: inherit; line-height: 1.5; resize: vertical; min-height: 100px; }
        .reply-form textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,.2); }
        .btn { display: inline-block; padding: 8px 20px; border-radius: 6px; font-size: .875rem; font-weight: 500; cursor: pointer; border: none; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .timeline-item { padding: 16px 0; border-bottom: 1px solid #f3f4f6; }
        .timeline-item:last-child { border-bottom: none; }
        .timeline-item .sender { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: .7rem; font-weight: 600; text-transform: uppercase; margin-right: 8px; }
        .sender-customer { background: #dbeafe; color: #1e40af; }
        .sender-bot { background: #ede9fe; color: #6b21a8; }
        .sender-admin { background: #d1fae5; color: #065f46; }
        .timeline-item .time { font-size: .75rem; color: #9ca3af; }
        .timeline-item .text { margin-top: 8px; font-size: .875rem; white-space: pre-wrap; word-break: break-word; }
        .text-muted { color: #6b7280; }
    </style>
    @endif
</head>
<body>
    <div class="container">
        <a href="/dashboard" class="back-link">&larr; Back to Dashboard</a>

        <div class="card customer-header">
            <h1>{{ $customer->display_name ?? $customer->platform_user_id }}</h1>
            <p class="meta">
                @if ($customer->username)
                    &#64;{{ $customer->username }}
                    <span style="margin:0 6px">&middot;</span>
                @endif
                <span class="platform-tag">{{ $customer->platform }}</span>
                <span style="margin:0 6px">&middot;</span>
                ID: {{ $customer->platform_user_id }}
            </p>
            @if ($conversation)
                @php
                    $status = $conversation->status;
                    $statusLabel = match($status) {
                        'resolved' => 'Resolved',
                        'in_chat' => 'In Chat',
                        'Needs Reply' => 'Needs Reply',
                        default => $status,
                    };
                    $statusClass = match($status) {
                        'resolved' => 'status-resolved',
                        'in_chat' => 'status-in_chat',
                        'Needs Reply' => 'status-needs_reply',
                        default => 'status-new',
                    };
                @endphp
                <span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
            @endif
        </div>

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

        @if ($customer->platform === 'telegram')
        <div class="card reply-form">
            <h2>Reply to Customer</h2>
            <form method="POST" action="/customers/{{ $customer->platform }}/{{ $customer->platform_user_id }}/reply">
                @csrf
                <textarea name="message" rows="4" maxlength="4000"
                    placeholder="Type your reply..." required>{{ old('message') }}</textarea>
                <div style="margin-top:12px">
                    <button type="submit" class="btn btn-primary">Send Reply</button>
                </div>
            </form>
        </div>
        @endif

        <div class="card">
            <h2>Timeline</h2>
            @forelse ($messages as $message)
                <div class="timeline-item">
                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <div>
                            @php
                                $senderLabel = match($message->sender_type) {
                                    'customer' => 'Customer',
                                    'bot' => 'Bot',
                                    'admin' => 'Admin',
                                    default => $message->sender_type,
                                };
                            @endphp
                            <span class="sender sender-{{ $message->sender_type }}">{{ $senderLabel }}</span>
                        </div>
                        <span class="time">{{ $message->created_at->format('Y-m-d H:i:s') }}</span>
                    </div>
                    <p class="text">{{ $message->text }}</p>
                </div>
            @empty
                <p class="text-muted" style="text-align:center;padding:24px 0">No messages yet.</p>
            @endforelse
        </div>
    </div>
</body>
</html>
