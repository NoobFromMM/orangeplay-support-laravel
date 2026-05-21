<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cases — OrangePlay Support</title>
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: #f8f9fa; color: #1a1a2e; line-height: 1.6; min-height: 100vh; }
        .nav { background: #fff; border-bottom: 1px solid #e9ecef; padding: 0 16px; }
        .nav-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; height: 56px; }
        .nav-brand { font-size: 1rem; font-weight: 700; color: #1a1a2e; text-decoration: none; letter-spacing: -.02em; }
        .nav-brand span { color: #2563eb; }
        .nav-links { display:flex; gap:16px; align-items:center; font-size:.9rem; }
        .nav-links a { color:#2563eb; text-decoration:none; }
        .nav-links a:hover { text-decoration:underline; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px 16px; }
        .page-header { display:flex; align-items:baseline; justify-content:space-between; gap:12px; margin-bottom: 18px; flex-wrap: wrap; }
        .page-header h1 { font-size: 1.5rem; font-weight: 700; }
        .count { font-size: .8rem; color: #6b7280; background: #e5e7eb; padding: 3px 12px; border-radius: 9999px; }
        .card { background: #fff; border: 1px solid #e9ecef; border-radius: 12px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f9fafb; }
        th { text-align: left; padding: 10px 16px; font-size: .7rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid #e5e7eb; }
        td { padding: 14px 16px; font-size: .875rem; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        tbody tr { cursor: pointer; transition: background .1s; }
        tbody tr:hover { background: #f0f4ff; }
        tbody tr:last-child td { border-bottom: none; }
        .case-title { font-weight: 600; color: #111827; }
        .case-meta { color: #6b7280; font-size: .8rem; margin-top: 2px; }
        .badge { display:inline-flex; align-items:center; padding: 4px 10px; border-radius:9999px; font-size:.72rem; font-weight:600; white-space:nowrap; }
        .badge-open { background:#eff6ff; color:#2563eb; }
        .badge-in_progress { background:#fef3c7; color:#d97706; }
        .badge-resolved { background:#ecfdf5; color:#059669; }
        .badge-rejected { background:#fef2f2; color:#dc2626; }
        .badge-low { background:#f3f4f6; color:#6b7280; }
        .badge-normal { background:#ecfeff; color:#0f766e; }
        .badge-high { background:#fff1f2; color:#be123c; }
        .empty { padding: 70px 20px; text-align:center; color:#9ca3af; }
        .empty h2 { font-size: 1rem; color:#6b7280; margin-bottom: 6px; }
        .empty p { font-size: .9rem; }
        a { color:#2563eb; text-decoration:none; }
        a:hover { text-decoration:underline; }
        @media (max-width: 720px) {
            th:nth-child(4), td:nth-child(4), th:nth-child(5), td:nth-child(5) { display:none; }
            th, td { padding: 12px 10px; }
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

        <div class="page-header">
            <h1>Support Cases</h1>
            <span class="count">{{ $cases->count() }} total</span>
        </div>

        <div class="card">
            @if ($cases->isEmpty())
                <div class="empty">
                    <h2>No cases yet</h2>
                    <p>Create one from a customer message inside a conversation.</p>
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Case</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Customer</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cases as $case)
                            <tr onclick="location.href='{{ route('cases.show', $case) }}'">
                                <td>
                                    <div class="case-title">{{ $case->displayCode() }} {{ $case->title }}</div>
                                    <div class="case-meta">{{ \App\Models\SupportCase::labelForCategory($case->category) }}</div>
                                </td>
                                <td><span class="badge badge-{{ $case->status }}">{{ \App\Models\SupportCase::labelForStatus($case->status) }}</span></td>
                                <td><span class="badge badge-{{ $case->priority }}">{{ \App\Models\SupportCase::labelForPriority($case->priority) }}</span></td>
                                <td>
                                    <div>{{ $case->customer?->display_name ?? $case->platform_user_id ?? 'Unknown customer' }}</div>
                                    <div class="case-meta">{{ $case->platform }}</div>
                                </td>
                                <td>{{ $case->created_at?->timezone('Asia/Yangon')->format('M j, Y g:ia') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</body>
</html>
