<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit FAQ — OrangePlay Support</title>
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f8f9fa;color:#1a1a2e;line-height:1.6}
        .nav{background:#fff;border-bottom:1px solid #e9ecef;padding:0 16px}
        .nav-inner{max-width:700px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;height:56px}
        .nav-brand{font-size:1rem;font-weight:700;color:#1a1a2e;text-decoration:none}
        .nav-brand span{color:#2563eb}
        .back-link{font-size:.85rem;color:#2563eb;text-decoration:none}
        .container{max-width:700px;margin:0 auto;padding:24px 16px}
        h1{font-size:1.5rem;font-weight:700;margin-bottom:20px}
        .card{background:#fff;border-radius:12px;border:1px solid #e9ecef;padding:24px}
        .btn{display:inline-flex;align-items:center;gap:4px;padding:8px 18px;border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:background .15s}
        .btn-primary{background:#2563eb;color:#fff}
        .btn-primary:hover{background:#1d4ed8}
        .btn-secondary{border:1px solid #d1d5db;background:#fff;color:#374151}
        .form-group{margin-bottom:16px}
        .form-group label{display:block;font-size:.85rem;font-weight:600;margin-bottom:4px;color:#374151}
        .form-group input,.form-group textarea,.form-group select{width:100%;border:1px solid #d1d5db;border-radius:8px;padding:10px 14px;font-size:.9rem;font-family:inherit;line-height:1.5}
        .form-group textarea{resize:vertical;min-height:100px}
        .form-group input:focus,.form-group textarea:focus,.form-group select:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
        .form-hint{font-size:.75rem;color:#9ca3af;margin-top:4px}
        .form-actions{display:flex;gap:10px;align-items:center}
        .alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:.85rem}
        .checkbox-group{display:flex;align-items:center;gap:8px}
        .checkbox-group input[type=checkbox]{width:auto}
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-inner">
            <a href="/dashboard" class="nav-brand">OrangePlay <span>Support</span></a>
            <a href="/dashboard/faqs" class="back-link">&larr; Back to FAQs</a>
        </div>
    </nav>

    <div class="container">
        <h1>Edit FAQ</h1>

        @if ($errors->any())
            <div class="alert-error">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="card">
            <form method="POST" action="/dashboard/faqs/{{ $faqEntry->id }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="intent_code">Intent Code</label>
                    <input type="text" name="intent_code" id="intent_code" value="{{ old('intent_code', $faqEntry->intent_code) }}" maxlength="100" required>
                    <div class="form-hint">Unique identifier for this FAQ entry.</div>
                </div>

                <div class="form-group">
                    <label for="category">Category</label>
                    <input type="text" name="category" id="category" value="{{ old('category', $faqEntry->category) }}" maxlength="100">
                    <div class="form-hint">Optional grouping label.</div>
                </div>

                <div class="form-group">
                    <label for="keywords">Keywords</label>
                    <textarea name="keywords" id="keywords" rows="5" required>{{ old('keywords', implode("\n", $faqEntry->keywords)) }}</textarea>
                    <div class="form-hint">One keyword per line. Customer message matching any keyword will trigger this answer.</div>
                </div>

                <div class="form-group">
                    <label for="answer_text">Answer Text</label>
                    <textarea name="answer_text" id="answer_text" rows="6" required>{{ old('answer_text', $faqEntry->answer_text) }}</textarea>
                    <div class="form-hint">The reply sent to the customer when a keyword matches.</div>
                </div>

                <div class="form-group">
                    <label for="priority">Priority</label>
                    <input type="number" name="priority" id="priority" value="{{ old('priority', $faqEntry->priority) }}" min="0" max="999">
                    <div class="form-hint">Higher = checked first.</div>
                </div>

                <div class="form-group checkbox-group">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $faqEntry->is_active) ? 'checked' : '' }}>
                    <label for="is_active">Active</label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update FAQ</button>
                    <a href="/dashboard/faqs" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
