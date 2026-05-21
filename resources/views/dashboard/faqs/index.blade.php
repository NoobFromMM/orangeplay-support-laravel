<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FAQ Manager — OrangePlay Support</title>
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f8f9fa;color:#1a1a2e;line-height:1.6}
        .nav{background:#fff;border-bottom:1px solid #e9ecef;padding:0 16px}
        .nav-inner{max-width:1360px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;height:56px}
        .nav-brand{font-size:1rem;font-weight:700;color:#1a1a2e;text-decoration:none}
        .nav-brand span{color:#2563eb}
        .nav-links{display:flex;gap:20px;font-size:.85rem}
        .nav-links a{color:#6b7280;text-decoration:none}
        .nav-links a:hover,.nav-links a.active{color:#2563eb}
        .container{max-width:1360px;margin:0 auto;padding:24px 16px}
        .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px}
        .page-header h1{font-size:1.5rem;font-weight:700}
        .btn{display:inline-flex;align-items:center;gap:4px;padding:8px 18px;border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:background .15s}
        .btn-primary{background:#2563eb;color:#fff}
        .btn-primary:hover{background:#1d4ed8}
        .btn-sm{padding:4px 10px;font-size:.75rem}
        .btn-success{background:#059669;color:#fff}
        .btn-success:hover{background:#047857}
        .btn-danger{background:#dc2626;color:#fff}
        .btn-danger:hover{background:#b91c1c}
        .btn-outline{border:1px solid #d1d5db;background:#fff;color:#374151}
        .btn-outline:hover{background:#f9fafb}
        .card{background:#fff;border-radius:12px;border:1px solid #e9ecef;overflow:hidden}
        table{width:100%;border-collapse:collapse}
        thead{background:#f9fafb}
        th{text-align:left;padding:10px 16px;font-size:.7rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #e5e7eb}
        td{padding:12px 16px;font-size:.85rem;border-bottom:1px solid #f3f4f6;vertical-align:middle}
        tbody tr:last-child td{border-bottom:none}
        tbody tr:hover{background:#fafbff}
        .status-badge{display:inline-block;padding:2px 10px;border-radius:9999px;font-size:.7rem;font-weight:600}
        .status-active{background:#ecfdf5;color:#059669}
        .status-inactive{background:#f3f4f6;color:#6b7280}
        .text-muted{color:#6b7280;font-size:.825rem}
        .alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:.85rem}
        .alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
        .empty{padding:48px 0;text-align:center;color:#9ca3af}
        .form-group{margin-bottom:16px}
        .form-group label{display:block;font-size:.85rem;font-weight:600;margin-bottom:4px;color:#374151}
        .form-group input,.form-group textarea,.form-group select{width:100%;border:1px solid #d1d5db;border-radius:8px;padding:10px 14px;font-size:.9rem;font-family:inherit;line-height:1.5}
        .form-group textarea{resize:vertical;min-height:100px}
        .form-group input:focus,.form-group textarea:focus,.form-group select:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
        .form-hint{font-size:.75rem;color:#9ca3af;margin-top:4px}
        .form-actions{display:flex;gap:10px;align-items:center}
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-inner">
            <a href="/dashboard" class="nav-brand">OrangePlay <span>Support</span></a>
            <div class="nav-links">
                <a href="/dashboard">Dashboard</a>
                <a href="/dashboard/faqs" class="active">FAQ Manager</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>FAQ Manager</h1>
            <a href="/dashboard/faqs/create" class="btn btn-primary">+ Add FAQ</a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Intent Code</th>
                        <th>Category</th>
                        <th>Keywords</th>
                        <th>Answer</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($faqs as $faq)
                        <tr>
                            <td style="font-weight:600">{{ $faq->intent_code }}</td>
                            <td>{{ $faq->category ?? '-' }}</td>
                            <td class="text-muted">{{ Str::limit(implode(', ', $faq->keywords), 50) }}</td>
                            <td class="text-muted">{{ Str::limit($faq->answer_text, 60) }}</td>
                            <td>{{ $faq->priority }}</td>
                            <td>
                                <span class="status-badge {{ $faq->is_active ? 'status-active' : 'status-inactive' }}">
                                    {{ $faq->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;gap:6px">
                                    <a href="/dashboard/faqs/{{ $faq->id }}/edit" class="btn btn-sm btn-outline">Edit</a>
                                    <form method="POST" action="/dashboard/faqs/{{ $faq->id }}/toggle" style="display:inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline">
                                            {{ $faq->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty">No FAQs yet. Click "Add FAQ" to create one.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
