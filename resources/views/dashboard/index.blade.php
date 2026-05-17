<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OrangePlay Support</title>
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; color: #1f2937; line-height: 1.5; }
        .container { max-width: 900px; margin: 0 auto; padding: 24px 16px; }
        h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 20px; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); padding: 20px; }
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 9999px; font-size: .75rem; font-weight: 600; }
        .status-resolved { background: #d1fae5; color: #065f46; }
        .status-in_chat { background: #fef3c7; color: #92400e; }
        .status-needs_reply, .status-new, .status-open { background: #dbeafe; color: #1e40af; }
        .platform-tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: .75rem; background: #e0e7ff; color: #3730a3; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px 16px; font-size: .75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; border-bottom: 1px solid #e5e7eb; }
        td { padding: 12px 16px; font-size: .875rem; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        tr:hover { background: #f9fafb; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .text-muted { color: #6b7280; font-size: .875rem; }
        .empty { padding: 48px 0; text-align: center; color: #9ca3af; }
        .back-link { display: inline-block; margin-bottom: 16px; font-size: .875rem; }
    </style>
    @endif
</head>
<body>
    <div class="container">
        <h1>OrangePlay Support</h1>

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
                        @endphp
                        <tr>
                            <td>
                                <a href="/customers/{{ $customer->platform }}/{{ $customer->platform_user_id }}" style="font-weight:500">
                                    {{ $customer->display_name ?? $customer->platform_user_id }}
                                </a>
                                @if ($customer->username)
                                    <br><span class="text-muted">&#64;{{ $customer->username }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="platform-tag">{{ $customer->platform }}</span>
                            </td>
                            <td class="text-muted">
                                {{ $latestMsg ? Str::limit($latestMsg->text, 50) : '-' }}
                            </td>
                            <td>
                                @php
                                    $status = $latestConv->status ?? '';
                                    $statusLabel = match($status) {
                                        'resolved' => 'Resolved',
                                        'in_chat' => 'In Chat',
                                        'Needs Reply' => 'Needs Reply',
                                        default => $status ?: '-',
                                    };
                                @endphp
                                <span class="status-badge {{ str_contains($status, 'Needs Reply') ? 'status-needs_reply' : 'status-'.str_replace(' ', '_', strtolower($status)) }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="text-muted">
                                {{ $latestConv?->last_message_at?->diffForHumans() ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty">No customers yet. Send a Telegram message to get started.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
