<?php

namespace App\Http\Controllers;

use App\Models\FaqEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaqEntryController extends Controller
{
    public function index(): View
    {
        $faqs = FaqEntry::orderBy('priority', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('dashboard.faqs.index', compact('faqs'));
    }

    public function create(): View
    {
        return view('dashboard.faqs.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'intent_code' => ['required', 'string', 'max:100', 'unique:faq_entries,intent_code'],
            'category' => ['nullable', 'string', 'max:100'],
            'keywords' => ['required', 'string'],
            'answer_text' => ['required', 'string'],
            'priority' => ['integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ]);

        FaqEntry::create([
            'intent_code' => $validated['intent_code'],
            'category' => $validated['category'] ?? null,
            'keywords' => $this->parseKeywords($validated['keywords']),
            'answer_text' => $validated['answer_text'],
            'priority' => $validated['priority'] ?? 50,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect('/dashboard/faqs')->with('success', 'FAQ created.');
    }

    public function edit(FaqEntry $faqEntry): View
    {
        return view('dashboard.faqs.edit', compact('faqEntry'));
    }

    public function update(Request $request, FaqEntry $faqEntry): RedirectResponse
    {
        $validated = $request->validate([
            'intent_code' => ['required', 'string', 'max:100', 'unique:faq_entries,intent_code,' . $faqEntry->id],
            'category' => ['nullable', 'string', 'max:100'],
            'keywords' => ['required', 'string'],
            'answer_text' => ['required', 'string'],
            'priority' => ['integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ]);

        $faqEntry->update([
            'intent_code' => $validated['intent_code'],
            'category' => $validated['category'] ?? null,
            'keywords' => $this->parseKeywords($validated['keywords']),
            'answer_text' => $validated['answer_text'],
            'priority' => $validated['priority'] ?? 50,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect('/dashboard/faqs')->with('success', 'FAQ updated.');
    }

    public function toggle(FaqEntry $faqEntry): RedirectResponse
    {
        $faqEntry->update(['is_active' => ! $faqEntry->is_active]);

        return back()->with('success', $faqEntry->is_active ? 'FAQ activated.' : 'FAQ deactivated.');
    }

    protected function parseKeywords(string $input): array
    {
        $lines = explode("\n", $input);
        $keywords = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $keywords[] = $trimmed;
            }
        }

        return array_values(array_unique($keywords));
    }
}
