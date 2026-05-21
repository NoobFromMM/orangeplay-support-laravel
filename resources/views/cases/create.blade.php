<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Case — OrangePlay Support</title>
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
        .title { font-size:1.35rem; font-weight:700; margin-bottom:4px; }
        .subtitle { color:#6b7280; font-size:.9rem; }
        .section-title { font-size:.8rem; text-transform:uppercase; letter-spacing:.04em; color:#6b7280; margin-bottom:10px; }
        .source-box { background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:14px; white-space:pre-wrap; word-break:break-word; }
        .image-preview { max-width: 320px; width:100%; border-radius: 12px; border: 1px solid #e5e7eb; display:block; margin-top:12px; }
        .source-list { display:flex; flex-direction:column; gap:10px; }
        .source-item {
            border:1px solid #e5e7eb;
            border-radius:12px;
            background:#fff;
            padding:12px 14px;
            display:flex;
            gap:10px;
            align-items:flex-start;
        }
        .source-item input { margin-top:4px; flex-shrink:0; }
        .source-item-body { min-width:0; flex:1; }
        .source-item-title { font-size:.9rem; font-weight:700; color:#111827; }
        .source-item-meta { font-size:.78rem; color:#6b7280; margin-top:2px; }
        .source-item-preview { margin-top:8px; padding:10px 12px; background:#f8fafc; border-radius:10px; border:1px solid #e5e7eb; white-space:pre-wrap; word-break:break-word; font-size:.84rem; color:#334155; }
        .source-item-image { max-width: 120px; width:100%; border-radius: 10px; border:1px solid #e5e7eb; display:block; margin-top:8px; }
        .source-item-selected { border-color:#bfdbfe; background:#eff6ff; }
        .form-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:14px; }
        label { display:block; font-size:.8rem; font-weight:600; color:#374151; margin-bottom:6px; }
        input, select, textarea {
            width:100%; border:1px solid #d1d5db; border-radius:8px; padding:12px;
            font: inherit; font-size:.9rem; background:#fff;
        }
        textarea { min-height: 120px; resize: vertical; }
        input:focus, select:focus, textarea:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
        .help { font-size:.78rem; color:#6b7280; margin-top:6px; }
        .full { grid-column: 1 / -1; }
        .actions { display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap; margin-top: 10px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:10px 18px; border-radius:8px; border:none; cursor:pointer; font-size:.875rem; font-weight:600; }
        .btn-primary { background:#2563eb; color:#fff; }
        .btn-primary:hover { background:#1d4ed8; }
        .btn-secondary { background:#fff; color:#1d4ed8; border:1px solid #bfdbfe; text-decoration:none; }
        .btn-secondary:hover { background:#eff6ff; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: .85rem; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        @media (max-width: 720px) { .form-grid { grid-template-columns: 1fr; } }
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
        @if ($errors->any())
            <div class="alert alert-error">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="card">
            <div class="title">Create Support Case</div>
            <div class="subtitle">Create a case from the latest or a recent inbound message in this conversation.</div>
        </div>

        <div class="card">
            <div class="section-title">Source Message</div>
            <div class="source-box">
                {{ $selectedMessage ? 'Default selection: latest inbound customer message.' : 'No recent inbound customer messages were found.' }}
            </div>
            @if ($selectedMessage && ($sourceText || $selectedMessage->message_type !== 'text'))
                <div style="margin-top:12px">
                    <div class="source-item-preview">
                        <strong>{{ ucfirst($selectedMessage->message_type) }} message</strong>
                        <div style="margin-top:6px">{{ $sourceText ?: 'Attachment without text' }}</div>
                    </div>
                    @if (in_array($selectedMessage->message_type, ['image', 'file'], true) && ($selectedMessage->metadata['telegram_file_id'] ?? null))
                        <img src="/telegram/file/{{ $selectedMessage->metadata['telegram_file_id'] }}" class="image-preview" alt="Source message preview">
                    @endif
                </div>
            @endif
        </div>

        <div class="card">
            <form method="POST" action="{{ route('customers.cases.store', ['platform' => $customer->platform, 'platformUserId' => $customer->platform_user_id]) }}">
                @csrf
                <input type="hidden" name="message_id" value="{{ old('message_id', $selectedMessageId) }}">
                <input type="hidden" name="status" value="open">
                <div class="section-title">Recent Inbound Messages</div>
                <div class="source-list">
                    @forelse ($sourceMessages as $candidate)
                        @php
                            $previewText = $candidate->text ?: ($candidate->metadata['caption'] ?? null);
                            $isSelected = (string) old('message_id', $selectedMessageId) === (string) $candidate->id;
                            $hasAttachment = in_array($candidate->message_type, ['image', 'file'], true);
                        @endphp
                        <label class="source-item {{ $isSelected ? 'source-item-selected' : '' }}">
                            <input type="radio" name="message_id" value="{{ $candidate->id }}" @checked($isSelected) required>
                            <div class="source-item-body">
                                <div class="source-item-title">{{ ucfirst($candidate->message_type) }} message</div>
                                <div class="source-item-meta">{{ $candidate->created_at?->timezone('Asia/Yangon')->format('M j, Y g:ia') }}</div>
                                @if ($previewText)
                                    <div class="source-item-preview">{{ $previewText }}</div>
                                @endif
                                @if ($hasAttachment && ($candidate->metadata['telegram_file_id'] ?? null))
                                    <img src="/telegram/file/{{ $candidate->metadata['telegram_file_id'] }}" class="source-item-image" alt="Message preview">
                                @endif
                            </div>
                        </label>
                    @empty
                        <div class="source-box">No source messages found.</div>
                    @endforelse
                </div>
                <div class="form-grid">
                    <div>
                        <label for="category">Category</label>
                        <select id="category" name="category" required>
                            <option value="">Choose a category</option>
                            @foreach (\App\Models\SupportCase::categoryOptions() as $category)
                                <option value="{{ $category }}" @selected(old('category') === $category)>{{ \App\Models\SupportCase::labelForCategory($category) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority" required>
                            @foreach (\App\Models\SupportCase::priorityOptions() as $priority)
                                <option value="{{ $priority }}" @selected(old('priority', 'normal') === $priority)>{{ \App\Models\SupportCase::labelForPriority($priority) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="full">
                        <label for="title">Title</label>
                        <input id="title" name="title" type="text" maxlength="200" value="{{ old('title', $prefilledTitle) }}" required>
                    </div>
                    <div class="full">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" maxlength="5000" placeholder="Add any extra context...">{{ old('description') }}</textarea>
                    </div>
                    <div class="full">
                        <label for="admin_note">Admin Note</label>
                        <textarea id="admin_note" name="admin_note" maxlength="5000" placeholder="Internal note for support team...">{{ old('admin_note') }}</textarea>
                    </div>
                </div>
                <div class="actions">
                    <a href="/customers/{{ $customer->platform }}/{{ $customer->platform_user_id }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Case</button>
                </div>
                <div class="help">Support cases stay separate from conversation status and bot pause.</div>
            </form>
        </div>
    </div>
</body>
</html>
