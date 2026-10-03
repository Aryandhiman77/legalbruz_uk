<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminBlogController extends Controller
{
    public function index(Request $request): View
    {
        $posts = Blog::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = (string) $request->string('search');
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('author_name', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                match ((string) $request->string('status')) {
                    'published' => $query->where('status', 'published')
                        ->whereNotNull('published_at')
                        ->where('published_at', '<=', now()),
                    'scheduled' => $query->where('status', 'published')
                        ->where('published_at', '>', now()),
                    'draft' => $query->where('status', 'draft'),
                    default => null,
                };
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.blogs.index', compact('posts'));
    }

    public function create(): View
    {
        return view('admin.blogs.create', [
            'blog' => new Blog([
                'category' => 'Insights',
                'author_name' => 'Legal Bruz Team',
                'status' => 'draft',
                'published_at' => now(config('app.timezone', 'Europe/London')),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['slug'] = Blog::uniqueSlug($data['title']);
        $data = $this->storeImages($request, $data);
        $blog = Blog::create($data);

        return redirect()
            ->route('admin.blogs.edit', $blog)
            ->with('success', 'Blog post created successfully.');
    }

    public function edit(Blog $blog): View
    {
        return view('admin.blogs.edit', compact('blog'));
    }

    public function update(Request $request, Blog $blog): RedirectResponse
    {
        $data = $this->validatedData($request);
        if ($blog->title !== $data['title']) {
            $data['slug'] = Blog::uniqueSlug($data['title'], $blog->id);
        }

        $data = $this->storeImages($request, $data, $blog);
        $blog->update($data);

        return redirect()
            ->route('admin.blogs.edit', $blog)
            ->with('success', 'Blog post updated successfully.');
    }

    public function destroy(Blog $blog): RedirectResponse
    {
        Storage::disk('public')->delete(array_filter([
            $blog->featured_image_path,
            $blog->og_image_path,
        ]));
        $blog->delete();

        return redirect()
            ->route('admin.blogs.index')
            ->with('success', 'Blog post deleted successfully.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['required', 'string', 'max:1000'],
            'content' => ['required', 'string', 'min:100', 'max:100000'],
            'category' => ['required', 'string', 'max:120'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'author_name' => ['required', 'string', 'max:120'],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'featured_image_alt' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['nullable', Rule::in(['0', '1'])],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:170'],
            'seo_keywords' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url:http,https', 'max:500'],
            'og_title' => ['nullable', 'string', 'max:100'],
            'og_description' => ['nullable', 'string', 'max:220'],
            'og_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'schema_markup' => ['nullable', 'json', 'max:50000'],
            'remove_featured_image' => ['nullable', 'boolean'],
            'remove_og_image' => ['nullable', 'boolean'],
        ]);

        $data['tags'] = collect(explode(',', $data['tags'] ?? ''))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $data['content'] = $this->sanitizeArticleHtml($data['content']);
        $data['is_featured'] = $request->boolean('is_featured');
        if (filled($data['published_at'] ?? null)) {
            $data['published_at'] = Carbon::createFromFormat(
                'Y-m-d\TH:i',
                $data['published_at'],
                config('app.timezone', 'Europe/London')
            )->utc();
        }
        if (filled($data['schema_markup'] ?? null)) {
            $schema = json_decode($data['schema_markup'], true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($schema)
                || array_is_list($schema)
                || ! isset($schema['@context'])
                || (! isset($schema['@type']) && ! isset($schema['@graph']))) {
                throw ValidationException::withMessages([
                    'schema_markup' => 'Schema must be a JSON object containing @context and either @type or @graph.',
                ]);
            }

            $data['schema_markup'] = json_encode(
                $schema,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            );
        } else {
            $data['schema_markup'] = null;
        }

        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        unset($data['featured_image'], $data['og_image'], $data['remove_featured_image'], $data['remove_og_image']);

        return $data;
    }

    private function sanitizeArticleHtml(string $html): string
    {
        if (! preg_match('/<[a-z][\s\S]*>/i', $html)) {
            return $html;
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="article-content-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($document);
        $blockedTags = 'script|style|iframe|object|embed|form|input|button|textarea|select|option|link|meta|base';

        foreach ($xpath->query('//*[contains("|'.$blockedTags.'|", concat("|", translate(local-name(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "|"))]') as $node) {
            $node->parentNode?->removeChild($node);
        }

        foreach ($xpath->query('//*') as $element) {
            foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
                $name = strtolower($attribute->name);
                $value = trim($attribute->value);

                if (str_starts_with($name, 'on')
                    || $name === 'style'
                    || (in_array($name, ['href', 'src'], true)
                        && preg_match('/^(?:javascript|data):/i', preg_replace('/[\x00-\x20]+/', '', $value)))) {
                    $element->removeAttribute($attribute->name);
                }
            }
        }

        $root = $document->getElementById('article-content-root');
        $safeHtml = '';

        foreach ($root?->childNodes ?? [] as $child) {
            $safeHtml .= $document->saveHTML($child);
        }

        return trim($safeHtml);
    }

    private function storeImages(Request $request, array $data, ?Blog $blog = null): array
    {
        if ($request->boolean('remove_featured_image') && $blog?->featured_image_path) {
            Storage::disk('public')->delete($blog->featured_image_path);
            $data['featured_image_path'] = null;
        }

        if ($request->hasFile('featured_image')) {
            if ($blog?->featured_image_path) {
                Storage::disk('public')->delete($blog->featured_image_path);
            }
            $data['featured_image_path'] = $request->file('featured_image')->store('blogs/featured', 'public');
        }

        if ($request->boolean('remove_og_image') && $blog?->og_image_path) {
            Storage::disk('public')->delete($blog->og_image_path);
            $data['og_image_path'] = null;
        }

        if ($request->hasFile('og_image')) {
            if ($blog?->og_image_path) {
                Storage::disk('public')->delete($blog->og_image_path);
            }
            $data['og_image_path'] = $request->file('og_image')->store('blogs/social', 'public');
        }

        return $data;
    }
}
