<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Blog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_blog_only_lists_currently_published_posts(): void
    {
        $published = $this->createBlog(['title' => 'Published Trademark Guide']);
        $draft = $this->createBlog(['title' => 'Private Draft', 'status' => 'draft']);
        $scheduled = $this->createBlog([
            'title' => 'Scheduled Article',
            'published_at' => now()->addDay(),
        ]);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee($published->title)
            ->assertDontSee($draft->title)
            ->assertDontSee($scheduled->title);

        $this->get(route('blog.show', $draft))->assertNotFound();
        $this->get(route('blog.show', $scheduled))->assertNotFound();
    }

    public function test_a_single_featured_article_also_appears_in_latest_articles(): void
    {
        $featured = $this->createBlog([
            'title' => 'Featured Trademark Article',
            'is_featured' => true,
        ]);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee($featured->title)
            ->assertSee('href="'.route('blog.show', ['blog' => $featured->slug]).'"', false)
            ->assertSee('aria-label="Read '.$featured->title.'"', false)
            ->assertSee('1 article')
            ->assertDontSee('No articles found')
            ->assertDontSee('No articles published yet');
    }

    public function test_article_outputs_metadata_and_only_manually_supplied_schema(): void
    {
        $blog = $this->createBlog([
            'seo_title' => 'Indian Trademark Filing Guide',
            'seo_description' => 'A practical guide to filing and protecting a trademark in India.',
            'seo_keywords' => 'trademark filing, India trademark',
            'og_title' => 'Trademark Filing Made Clear',
            'og_description' => 'Understand the trademark filing process.',
            'schema_markup' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'BlogPosting',
                'headline' => 'Manual trademark schema',
            ]),
        ]);

        $response = $this->get(route('blog.show', $blog));

        $response->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('blog.show', $blog).'">', false)
            ->assertSee('Indian Trademark Filing Guide | Legal Bruz')
            ->assertSee('property="og:type" content="article"', false)
            ->assertSee('property="article:published_time"', false)
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSee('Manual trademark schema')
            ->assertSee('name="keywords" content="trademark filing, India trademark"', false);

        $withoutSchema = $this->createBlog(['title' => 'Article Without Schema']);
        $this->get(route('blog.show', $withoutSchema))
            ->assertOk()
            ->assertDontSee('type="application/ld+json"', false);
    }

    public function test_admin_can_create_update_and_delete_blog_with_images(): void
    {
        Storage::fake('public');
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.blogs.store'), $this->blogPayload([
                'title' => 'How to Protect a Brand',
                'featured_image' => UploadedFile::fake()->image('brand.webp', 1200, 700),
                'og_image' => UploadedFile::fake()->image('social.png', 1200, 630),
            ]))
            ->assertRedirect();

        $blog = Blog::where('title', 'How to Protect a Brand')->firstOrFail();
        $this->assertTrue($blog->is_public);
        Storage::disk('public')->assertExists($blog->featured_image_path);
        Storage::disk('public')->assertExists($blog->og_image_path);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.blogs.update', $blog), $this->blogPayload([
                'title' => 'How to Protect Your Brand',
                'seo_title' => 'How to Protect Your Brand in India',
                'remove_og_image' => '1',
            ]))
            ->assertRedirect(route('admin.blogs.edit', $blog));

        $this->assertDatabaseHas('blogs', [
            'id' => $blog->id,
            'title' => 'How to Protect Your Brand',
            'seo_title' => 'How to Protect Your Brand in India',
            'og_image_path' => null,
        ]);

        $featuredPath = $blog->fresh()->featured_image_path;

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.blogs.destroy', $blog))
            ->assertRedirect(route('admin.blogs.index'));

        $this->assertDatabaseMissing('blogs', ['id' => $blog->id]);
        Storage::disk('public')->assertMissing($featuredPath);
    }

    public function test_sitemap_and_robots_include_public_discovery_information(): void
    {
        $blog = $this->createBlog();

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee(route('blog.show', $blog))
            ->assertSee(route('blog.index'));

        $this->get(route('robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin/')
            ->assertSee('Sitemap: '.route('sitemap'));
    }

    public function test_homepage_has_core_website_seo_metadata(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('<meta name="description"', false)
            ->assertSee('<link rel="canonical" href="'.route('landing').'">', false)
            ->assertSee('href="'.route('blog.index').'">Blogs</a>', false)
            ->assertSee('"@type":"Organization"', false);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('<h1>Blogs</h1>', false);
    }

    public function test_admin_can_open_blog_create_form(): void
    {
        $admin = $this->createAdmin();
        Carbon::setTestNow(Carbon::create(2026, 7, 29, 13, 15, 0, 'Europe/London'));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.blogs.create'))
            ->assertOk()
            ->assertSee('Custom schema markup')
            ->assertSee('ckeditor5/41.4.2/classic/ckeditor.js', false)
            ->assertSee('ClassicEditor', false)
            ->assertSee('id="blogEditorForm"', false)
            ->assertSee('articleEditor.getData()', false)
            ->assertSee('value="2026-07-29T13:15"', false)
            ->assertSee('UK local time (GMT/BST)');

        Carbon::setTestNow();
    }

    public function test_rich_article_content_is_saved_and_executable_markup_is_removed(): void
    {
        $admin = $this->createAdmin();
        $content = '<h2>Protecting a growing brand</h2>'
            .'<p>'.str_repeat('Useful guidance for Indian business owners. ', 5).'</p>'
            .'<script>alert("unsafe")</script>'
            .'<p><a href="javascript:alert(1)" onclick="alert(1)">Read the guide</a></p>';

        $this->actingAs($admin, 'admin')
            ->post(route('admin.blogs.store'), $this->blogPayload([
                'title' => 'Rich Text Trademark Guide',
                'content' => $content,
            ]))
            ->assertRedirect();

        $blog = Blog::where('title', 'Rich Text Trademark Guide')->firstOrFail();

        $this->assertStringContainsString('<h2>Protecting a growing brand</h2>', $blog->content);
        $this->assertStringNotContainsString('<script', $blog->content);
        $this->assertStringNotContainsString('javascript:', $blog->content);
        $this->assertStringNotContainsString('onclick=', $blog->content);

        $this->get(route('blog.show', $blog))
            ->assertOk()
            ->assertSee('<h2>Protecting a growing brand</h2>', false);
    }

    private function createBlog(array $overrides = []): Blog
    {
        return Blog::create(array_merge([
            'title' => 'A Practical Trademark Guide',
            'excerpt' => 'A concise introduction to the trademark registration process for Indian businesses.',
            'content' => str_repeat("## Trademark protection\n\nUseful guidance for business owners and growing brands.\n\n", 3),
            'category' => 'Trademarks',
            'tags' => ['trademarks', 'brand protection'],
            'author_name' => 'Legal Bruz Team',
            'status' => 'published',
            'published_at' => now()->subHour(),
            'is_featured' => false,
        ], $overrides));
    }

    private function blogPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'A Practical Trademark Guide',
            'excerpt' => 'A concise introduction to the trademark registration process for Indian businesses.',
            'content' => str_repeat("## Trademark protection\n\nUseful guidance for business owners and growing brands.\n\n", 3),
            'category' => 'Trademarks',
            'tags' => 'trademarks, brand protection',
            'author_name' => 'Legal Bruz Team',
            'status' => 'published',
            'published_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'is_featured' => '1',
            'seo_title' => '',
            'seo_description' => '',
            'seo_keywords' => '',
            'canonical_url' => '',
            'og_title' => '',
            'og_description' => '',
            'schema_markup' => '',
        ], $overrides);
    }

    private function createAdmin(): Admin
    {
        return Admin::create([
            'name' => 'Blog Admin',
            'email' => 'blog-admin@example.com',
            'password' => bcrypt('password'),
        ]);
    }
}
