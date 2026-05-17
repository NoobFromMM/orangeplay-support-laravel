<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — OrangePlay Support</title>
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
        .nav { background: #fff; border-bottom: 1px solid #e9ecef; padding: 0 24px; }
        .nav-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; height: 56px; }
        .nav-brand { font-size: 1rem; font-weight: 700; color: #1a1a2e; text-decoration: none; letter-spacing: -.02em; }
        .nav-brand span { color: #2563eb; }
        .nav-meta { font-size: .75rem; color: #6b7280; }
        .container { max-width: 1100px; margin: 0 auto; padding: 24px; }
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
        .page-header h1 { font-size: 1.5rem; font-weight: 700; }
        .page-header .count { font-size: .875rem; color: #6b7280; background: #e5e7eb; padding: 4px 12px; border-radius: 9999px; }
        .card { background: #fff; border-radius: 12px; border: 1px solid #e9ecef; overflow: hidden; }
        .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; flex-shrink: 0; }
        .status-dot-resolved { background: #10b981; }
        .status-dot-in_chat { background: #f59e0b; }
        .status-dot-needs_reply { background: #3b82f6; }
        .status-dot-new { background: #9ca3af; }
        .status-badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 9999px; font-size: .7rem; font-weight: 600; white-space: nowrap; }
        .status-resolved { background: #ecfdf5; color: #059669; }
        .status-in_chat { background: #fffbeb; color: #d97706; }
        .status-needs_reply { background: #eff6ff; color: #2563eb; }
        .status-new, .status-open { background: #f9fafb; color: #6b7280; }
        .platform-tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: .7rem; font-weight: 600; background: #eef2ff; color: #4338ca; text-transform: capitalize; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f9fafb; }
        th { text-align: left; padding: 12px 20px; font-size: .7rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; border-bottom: 1px solid #e5e7eb; }
        td { padding: 14px 20px; font-size: .875rem; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        tbody tr { transition: background .1s; }
        tbody tr:hover { background: #fafbff; }
        tbody tr:last-child td { border-bottom: none; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .customer-name { font-weight: 600; line-height: 1.3; }
        .customer-username { font-size: .8rem; color: #6b7280; margin-top: 2px; }
        .msg-preview { font-size: .825rem; color: #4b5563; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; word-break: break-all; }
        .text-muted { color: #6b7280; font-size: .825rem; }
        .empty { padding: 64px 0; text-align: center; color: #9ca3af; }
        .empty-icon { font-size: 2.5rem; margin-bottom: 12px; opacity: .4; }
        .empty p { font-size: .9rem; }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-inner">
            <a href="/dashboard" class="nav-brand">OrangePlay <span>Support</span></a>
            <span class="nav-meta">Dashboard</span>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>Customers</h1>
            <span class="count">{{ $customers->count() }} total</span>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Platform</th>
                        <th>Latest Message</th>
                        <th>Status</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        @php
                            $latestMsg = $customer->messages->first();
                            $latestConv = $customer->conversations->first();
                            $status = $latestConv->status ?? '';
                            $statusLabel = match($status) {
                                'resolved' => 'Resolved',
                                'in_chat' => 'In Chat',
                                'Needs Reply' => 'Needs Reply',
                                default => $status ?: '-',
                            };
                            $statusKey = str_contains($status, 'Needs Reply') ? 'needs_reply' : str_replace(' ', '_', strtolower($status));
                        @endphp
                        <tr>
                            <td>
                                <a href="/customers/{{ $customer->platform }}/{{ $customer->platform_user_id }}" class="customer-name">
                                    {{ $customer->display_name ?? $customer->platform_user_id }}
                                </a>
                                @if ($customer->username)
                                    <div class="customer-username">&#64;{{ $customer->username }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="platform-tag">{{ $customer->platform }}</span>
                            </td>
                            <td>
                                <div class="msg-preview">{{ $latestMsg ? Str::limit($latestMsg->text, 60) : '—' }}</div>
                            </td>
                            <td>
                                <span class="status-badge status-{{ $statusKey }}">
                                    <span class="status-dot status-dot-{{ $statusKey }}"></span>
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="text-muted">
                                {{ $latestConv?->last_message_at?->diffForHumans() ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty">
                                <div class="empty-icon">&#128172;</div>
                                <p>No customers yet.</p>
                                <p style="font-size:.8rem;margin-top:8px">Send a message to your Telegram bot to get started.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
