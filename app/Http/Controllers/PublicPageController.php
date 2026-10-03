<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\CmsPage;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicPageController extends Controller
{
    public function about(): View
    {
        return view('pages.about');
    }

    public function terms(): View
    {
        return $this->legalPage('pages.terms', CmsPage::TERMS);
    }

    public function privacy(): View
    {
        return $this->legalPage('pages.privacy', CmsPage::PRIVACY);
    }

    public function refund(): View
    {
        return $this->legalPage('pages.refund', CmsPage::REFUND);
    }

    private function legalPage(string $view, string $key): View
    {
        return view($view, [
            'legalPage' => CmsPage::findByKey($key),
        ]);
    }

    public function faq(Request $request): View|JsonResponse
    {
        $faqs = collect();
        $categories = collect();

        if (Schema::hasTable('faqs')) {
            $categories = Faq::query()
                ->published()
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category');

            $faqs = Faq::query()
                ->published()
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = (string) $request->string('search')->trim();

                    $query->where(function ($query) use ($search) {
                        $query->where('question', 'like', "%{$search}%")
                            ->orWhere('answer', 'like', "%{$search}%")
                            ->orWhere('category', 'like', "%{$search}%");
                    });
                })
                ->when($request->filled('category'), function ($query) use ($request) {
                    $query->where('category', $request->string('category'));
                })
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('pages.partials.faq-list', compact('faqs'))->render(),
                'count' => $faqs->count(),
            ]);
        }

        return view('pages.faq', compact('faqs', 'categories'));
    }

    public function contact(): View
    {
        return view('pages.contact', [
            'contactServices' => $this->contactServiceOptions(),
        ]);
    }

    public function submitContact(Request $request): RedirectResponse
    {
        $services = $this->contactServiceOptions();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', 'max:30'],
            'business_name' => ['nullable', 'string', 'max:180'],
            'service_interested' => ['required', 'string', Rule::in($services)],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['nullable', 'max:0'],
        ]);

        unset($validated['website']);
        $validated['subject'] = $validated['service_interested'];
        $validated['status'] = 'new';

        ContactMessage::create($validated);

        return redirect()
            ->route('contact')
            ->with('success', 'Thank you. Your message has been received and our team will get back to you soon.');
    }

    /**
     * @return array<int, string>
     */
    private function contactServiceOptions(): array
    {
        return collect(config('visitor_services'))
            ->pluck('label')
            ->push('Copyright Registration')
            ->push('Patent Registration')
            ->push('Examination Response')
            ->push('Other')
            ->unique()
            ->values()
            ->all();
    }
}
