<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminFaqController extends Controller
{
    public function index(Request $request): View
    {
        $faqs = Faq::query()
            ->where('category', 'like', 'UK%')
            ->when($request->filled('status'), fn ($query) => $query->where(
                'is_active',
                $request->string('status')->toString() === 'published'
            ))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = (string) $request->string('search');
                $query->where(function ($query) use ($search) {
                    $query->where('question', 'like', "%{$search}%")
                        ->orWhere('answer', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.faqs.index', compact('faqs'));
    }

    public function create(): View
    {
        return view('admin.faqs.create', [
            'faq' => new Faq([
                'category' => 'UK Trade Marks',
                'sort_order' => (Faq::max('sort_order') ?? 0) + 10,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $faq = Faq::create($this->validatedData($request));

        return redirect()
            ->route('admin.faqs.edit', $faq)
            ->with('success', 'FAQ created successfully.');
    }

    public function edit(Faq $faq): View
    {
        return view('admin.faqs.edit', compact('faq'));
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $faq->update($this->validatedData($request));

        return redirect()
            ->route('admin.faqs.edit', $faq)
            ->with('success', 'FAQ updated successfully.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return redirect()
            ->route('admin.faqs.index')
            ->with('success', 'FAQ deleted successfully.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:10000'],
            'category' => ['required', 'string', 'max:100'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', Rule::in(['0', '1'])],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['category'] = 'UK Trade Marks';

        return $data;
    }
}
