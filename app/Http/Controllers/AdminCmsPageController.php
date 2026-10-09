<?php

namespace App\Http\Controllers;

use App\Models\CmsPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class AdminCmsPageController extends Controller
{
    public function edit(string $key): View
    {
        abort_unless(in_array($key, CmsPage::editableKeys(), true), 404);

        CmsPage::ensureDefaults();

        $page = CmsPage::query()->where('key', $key)->first();
        $fallback = CmsPage::findByKey($key);

        if ($key === CmsPage::ABOUT) {
            return view('admin.cms-pages.about-edit', [
                'page' => $page,
                'fallback' => $fallback,
                'about' => CmsPage::aboutContent(),
            ]);
        }

        return view('admin.cms-pages.edit', [
            'page' => $page,
            'fallback' => $fallback,
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        abort_unless(in_array($key, CmsPage::editableKeys(), true), 404);

        if ($key === CmsPage::ABOUT) {
            return $this->updateAbout($request);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'content' => ['required', 'string', 'max:150000'],
            'is_active' => ['nullable', Rule::in(['1'])],
        ]);

        CmsPage::ensureDefaults();

        CmsPage::query()->updateOrCreate(
            ['key' => $key],
            [
                'title' => $validated['title'],
                'content' => $this->sanitizeContent($validated['content']),
                'is_active' => true,
            ],
        );

        CmsPage::flushPageCache($key);

        return redirect()
            ->route('admin.cms-pages.edit', $key)
            ->with('success', 'CMS page updated successfully.');
    }

    private function sanitizeContent(string $html): string
    {
        $html = preg_replace('#<(script|iframe|object|embed|form|input|button|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#\s+on[a-z]+\s*=\s*([\"\']).*?\1#is', '', $html) ?? $html;
        $html = preg_replace('#\s+(href|src)\s*=\s*([\"\'])\s*javascript:.*?\2#is', ' $1="#"', $html) ?? $html;

        return trim($html);
    }

    private function updateAbout(Request $request): RedirectResponse
    {
        $content = $this->aboutContentFromRequest($request);

        foreach (['hero', 'founder'] as $image) {
            $upload = $image.'_image_upload';
            if ($request->hasFile($upload)) {
                $content[$image.'_image'] = $request->file($upload)->store('cms/about', 'public');
            }
        }

        CmsPage::query()->updateOrCreate(
            ['key' => CmsPage::ABOUT],
            [
                'title' => 'About Us',
                'content' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'is_active' => true,
            ],
        );

        CmsPage::flushPageCache(CmsPage::ABOUT);

        return redirect()
            ->route('admin.cms-pages.edit', CmsPage::ABOUT)
            ->with('success', 'About page updated successfully.');
    }

    public function previewAbout(Request $request): View
    {
        $content = $this->aboutContentFromRequest($request);

        foreach (['hero', 'founder'] as $image) {
            $upload = $image.'_image_upload';
            if (! $request->hasFile($upload)) {
                continue;
            }

            $file = $request->file($upload);
            $content[$image.'_image'] = sprintf(
                'data:%s;base64,%s',
                $file->getMimeType() ?: 'image/png',
                base64_encode((string) file_get_contents($file->getRealPath())),
            );
        }

        return view('pages.about', [
            'aboutContent' => $content,
            'aboutPreview' => true,
        ]);
    }

    private function aboutContentFromRequest(Request $request): array
    {
        $textFields = $this->aboutTextFields();
        $richFields = $this->aboutRichFields();
        $rules = [];

        foreach ($textFields as $field) {
            $rules[$field] = ['required', 'string', 'max:1000'];
        }
        foreach ($richFields as $field) {
            $rules[$field] = ['required', 'string', 'max:30000'];
        }
        $rules['focus_items'] = ['required', 'string', 'max:5000'];
        $rules['hero_image_upload'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
        $rules['founder_image_upload'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];

        $validated = $request->validate($rules);
        $content = CmsPage::aboutContent();

        foreach ([...$textFields, 'focus_items'] as $field) {
            $content[$field] = trim($validated[$field]);
        }
        foreach ($richFields as $field) {
            $content[$field] = $this->sanitizeContent($validated[$field]);
        }

        return $content;
    }

    private function aboutTextFields(): array
    {
        return [
            'hero_eyebrow', 'hero_title', 'hero_title_accent', 'hero_principle_label', 'hero_principle_text',
            'hero_note_line_1', 'hero_note_line_2',
            'story_kicker', 'story_title', 'mission_title', 'vision_title',
            'founder_kicker', 'founder_title', 'founder_name', 'founder_role', 'focus_heading',
            'trust_kicker', 'trust_title', 'trust_1_title', 'trust_1_copy', 'trust_2_title',
            'trust_2_copy', 'trust_3_title', 'trust_3_copy', 'trust_4_title', 'trust_4_copy',
            'cta_kicker', 'cta_title', 'cta_label',
        ];
    }

    private function aboutRichFields(): array
    {
        return ['hero_copy', 'story_copy', 'mission_copy', 'vision_copy', 'founder_copy', 'cta_copy'];
    }
}
