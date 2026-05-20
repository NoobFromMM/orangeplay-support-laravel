<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $case->title }} — OrangePlay Support</title>
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: #f8f9fa; color: #1a1a2e; line-height: 1.6; min-height: 100vh; }
        .nav { background: #fff; border-bottom: 1px solid #e9ecef; padding: 0 16px; }
        .nav-inner { max-width: 1100px; margin: 0 auto; display:flex; align-items:center; justify-content:space-between; height:56px; }
        .nav-brand { font-size: 1rem; font-weight: 700; color:#1a1a2e; text-decoration:none; letter-spacing:-.02em; }
        .nav-brand span { color:#2563eb; }
        .nav-links { display:flex; gap:16px; align-items:center; font-size:.9rem; }
        .nav-links a { color:#2563eb; text-decoration:none; }
        .nav-links a:hover { text-decoration:underline; }
        .container { max-width: 1100px; margin: 0 auto; padding: 24px 16px; }
        .card { background:#fff; border:1px solid #e9ecef; border-radius:12px; padding:20px; margin-bottom:16px; }
        .header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
        .title { font-size:1.35rem; font-weight:700; line-height:1.35; }
        .subtitle { margin-top:4px; color:#6b7280; font-size:.9rem; }
        .badges { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
        .badge { display:inline-flex; align-items:center; padding:4px 10px; border-radius:9999px; font-size:.72rem; font-weight:600; white-space:nowrap; }
        .badge-open { background:#eff6ff; color:#2563eb; }
        .badge-in_progress { background:#fef3c7; color:#d97706; }
        .badge-resolved { background:#ecfdf5; color:#059669; }
        .badge-rejected { background:#fef2f2; color:#dc2626; }
        .badge-low { background:#f3f4f6; color:#6b7280; }
        .badge-normal { background:#ecfeff; color:#0f766e; }
        .badge-high { background:#fff1f2; color:#be123c; }
        .section-title { font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; color:#6b7280; margin-bottom:8px; }
        .grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px 16px; }
        .field-label { font-size:.75rem; color:#6b7280; text-transform:uppercase; letter-spacing:.03em; margin-bottom:4px; }
        .field-value { font-size:.95rem; color:#111827; word-break:break-word; }
        .source-box { background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:14px; white-space:pre-wrap; word-break:break-word; }
        .meta-pre { background:#0f172a; color:#e2e8f0; padding:12px; border-radius:10px; overflow:auto; font-size:.8rem; }
        .image-preview { max-width: 320px; width:100%; border-radius: 12px; border: 1px solid #e5e7eb; display:block; }
        a { color:#2563eb; text-decoration:none; }
        a:hover { text-decoration:underline; }
        @media (max-width: 720px) {
            .grid { grid-template-columns: 1fr; }
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
            <div style="background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:.85rem">{{ session('success') }}</div>
        @endif

        <div class="card">
            <div class="header">
                <div>
                    <div class="title">{{ $case->title }}</div>
                    <div class="subtitle">
                        @if ($case->customer)
                            <a href="/customers/{{ $case->platform }}/{{ $case->platform_user_id }}">Open source conversation</a>
                            <span style="color:#d1d5db;margin:0 6px;">&middot;</span>
                        @endif
                        {{ $case->platform }} / {{ $case->platform_user_id ?? 'unknown' }}
                    </div>
                </div>
                <div class="badges">
                    <span class="badge badge-{{ $case->status }}">{{ \App\Models\SupportCase::labelForStatus($case->status) }}</span>
                    <span class="badge badge-{{ $case->priority }}">{{ \App\Models\SupportCase::labelForPriority($case->priority) }}</span>
                    <span class="badge">{{ \App\Models\SupportCase::labelForCategory($case->category) }}</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="section-title">Case Details</div>
            <div class="grid">
                <div>
                    <div class="field-label">Customer</div>
                    <div class="field-value">{{ $case->customer?->display_name ?? $case->platform_user_id ?? 'Unknown customer' }}</div>
                </div>
                <div>
                    <div class="field-label">Conversation</div>
                    <div class="field-value">
                        @if ($case->conversation)
                            <a href="/customers/{{ $case->platform }}/{{ $case->platform_user_id }}">View conversation</a>
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div>
                    <div class="field-label">Created</div>
                    <div class="field-value">{{ $case->created_at?->timezone('Asia/Yangon')->format('M j, Y g:ia') }}</div>
                </div>
                <div>
                    <div class="field-label">Resolved</div>
                    <div class="field-value">{{ $case->resolved_at?->timezone('Asia/Yangon')->format('M j, Y g:ia') ?? '-' }}</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="section-title">Description</div>
            <div class="field-value">{{ $case->description ?: 'No description provided.' }}</div>
        </div>

        <div class="card">
            <div class="section-title">Source Message</div>
            @if ($case->source_text)
                <div class="source-box">{{ $case->source_text }}</div>
            @else
                <div class="source-box">No text source stored for this message.</div>
            @endif

            @if ($case->message?->message_type === 'image' && ($case->source_metadata['raw_message_metadata']['telegram_file_id'] ?? null))
                <div style="margin-top:14px">
                    <img
                        src="/telegram/file/{{ $case->source_metadata['raw_message_metadata']['telegram_file_id'] }}"
                        class="image-preview"
                        alt="Case source image preview"
                    >
                </div>
            @endif
        </div>

        <div class="card">
            <div class="section-title">Source Metadata</div>
            <pre class="meta-pre">{{ json_encode($case->source_metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>

        @if ($case->admin_note)
            <div class="card">
                <div class="section-title">Admin Note</div>
                <div class="source-box">{{ $case->admin_note }}</div>
            </div>
        @endif
    </div>
</body>
</html>
