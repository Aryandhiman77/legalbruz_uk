<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CmsPage;
use App\Models\ContactMessage;
use App\Models\TrademarkSearchReportRequest;
use App\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicInformationPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_information_pages_are_available(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Protecting Brands.')
            ->assertSee('Anshul Sharma')
            ->assertSee('anshul-sharma-founder.png', false);
        $this->get(route('terms'))->assertOk()->assertSee('Terms &amp; Conditions', false);
        $this->get(route('privacy'))->assertOk()->assertSee('Privacy Policy');
        $this->get('/flow-guide')->assertNotFound();
        $this->get(route('contact'))
            ->assertOk()
            ->assertSee("Let's Protect Your Brand", false)
            ->assertSee('Business Name')
            ->assertSee('Service Interested In')
            ->assertSee('<option value="Examination Report"', false)
            ->assertSee('<option value="Opposition Service"', false)
            ->assertSee('info@legalbruz.com')
            ->assertDontSee('support@legalbruz.com')
            ->assertDontSee('Ambala Office')
            ->assertDontSee('Ambala District Court Office')
            ->assertSee('506-508 woodfield court, Honeypot lane, stanmore- HA7 1JR')
            ->assertSee('Mon to Friday - 10AM to 5PM');
        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('Frequently Asked Questions')
            ->assertSee('--faq-search-control-height: 48px', false)
            ->assertSee('top: calc(var(--faq-search-control-height) / 2)', false)
            ->assertSee('.faq-search-shortcut { display: none; }', false);
    }

    public function test_legal_pages_render_content_saved_in_the_admin_cms(): void
    {
        $pages = [
            CmsPage::TERMS => ['route' => 'terms', 'title' => 'Updated Terms', 'content' => '<p>Terms managed by admin.</p>'],
            CmsPage::PRIVACY => ['route' => 'privacy', 'title' => 'Updated Privacy', 'content' => '<p>Privacy managed by admin.</p>'],
            CmsPage::REFUND => ['route' => 'refund', 'title' => 'Updated Refunds', 'content' => '<p>Refunds managed by admin.</p>'],
        ];

        foreach ($pages as $key => $page) {
            CmsPage::query()->updateOrCreate(
                ['key' => $key],
                [
                    'title' => $page['title'],
                    'content' => $page['content'],
                    'is_active' => true,
                ],
            );

            $this->get(route($page['route']))
                ->assertOk()
                ->assertSee($page['title'])
                ->assertSee($page['content'], false);
        }
    }

    public function test_official_social_profiles_are_linked_across_public_pages(): void
    {
        $socialUrls = collect(config('social_links'))->pluck('url')->all();

        foreach ([route('landing'), route('contact')] as $page) {
            $response = $this->get($page)->assertOk();

            foreach ($socialUrls as $url) {
                $response->assertSee($url);
            }
        }
    }

    public function test_only_published_faqs_are_visible(): void
    {
        Faq::create([
            'question' => 'A published question?',
            'answer' => 'A public answer.',
            'category' => 'General',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        Faq::create([
            'question' => 'A draft question?',
            'answer' => 'This must stay private.',
            'category' => 'General',
            'sort_order' => 2,
            'is_active' => false,
        ]);

        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('A published question?')
            ->assertDontSee('A draft question?');
    }

    public function test_public_faq_search_filters_questions_answers_and_categories(): void
    {
        Faq::create([
            'question' => 'How can I track an application?',
            'answer' => 'Use the dashboard to see progress.',
            'category' => 'Applications',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        Faq::create([
            'question' => 'Which payment methods are available?',
            'answer' => 'Payment options appear at checkout.',
            'category' => 'Payments',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->get(route('faq', ['search' => 'dashboard']))
            ->assertOk()
            ->assertSee('How can I track an application?')
            ->assertDontSee('Which payment methods are available?');

        $this->get(route('faq', ['search' => 'Payments']))
            ->assertOk()
            ->assertSee('Which payment methods are available?')
            ->assertDontSee('How can I track an application?');

        $this->get(route('faq', ['search' => 'nothing-matches-this']))
            ->assertOk()
            ->assertSee('No matching FAQs found');
    }

    public function test_public_faq_search_can_return_debounced_fetch_payload(): void
    {
        Faq::create([
            'question' => 'How do live search results work?',
            'answer' => 'They are fetched after typing pauses.',
            'category' => 'General',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->getJson(route('faq', ['search' => 'typing pauses']))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonStructure(['html', 'count'])
            ->assertJsonFragment(['count' => 1]);
    }

    public function test_contact_form_stores_a_valid_message(): void
    {
        $response = $this->post(route('contact.submit'), [
            'name' => 'Asha Sharma',
            'email' => 'asha@example.com',
            'phone' => '+91 99999 99999',
            'business_name' => 'Asha Brands',
            'service_interested' => 'UK Trade Mark Filing',
            'message' => 'I would like help filing a trademark application.',
        ]);

        $response->assertRedirect(route('contact'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas(ContactMessage::class, [
            'email' => 'asha@example.com',
            'phone' => '+91 99999 99999',
            'business_name' => 'Asha Brands',
            'service_interested' => 'UK Trade Mark Filing',
            'subject' => 'UK Trade Mark Filing',
            'status' => 'new',
        ]);
    }

    public function test_contact_form_accepts_examination_report_as_a_service(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertDontSee('<option value="Trademark Search Report"', false);

        $this->post(route('contact.submit'), [
            'name' => 'Jamie Carter',
            'email' => 'jamie@example.co.uk',
            'phone' => '+44 7123 456789',
            'business_name' => 'Carter Brands Ltd',
            'service_interested' => 'Examination Report',
            'message' => 'I need help responding to a UKIPO examination report.',
        ])
            ->assertRedirect(route('contact'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas(ContactMessage::class, [
            'email' => 'jamie@example.co.uk',
            'service_interested' => 'Examination Report',
            'subject' => 'Examination Report',
        ]);
    }

    public function test_trademark_search_report_form_stores_a_report_request(): void
    {
        $this->get(route('trademark-search-report.create'))
            ->assertOk()
            ->assertSee('Trademark Search Report')
            ->assertSee('Goods, Services or Business Activity');

        $response = $this->post(route('trademark-search-report.store'), [
            'name' => 'Alex Morgan',
            'email' => 'alex@example.co.uk',
            'phone' => '+44 7700 900123',
            'brand_name' => 'North Pine',
            'business_activity' => 'Online retail services for sustainable home and lifestyle products.',
        ]);

        $reportRequest = TrademarkSearchReportRequest::query()->firstOrFail();
        $response->assertRedirect(route('trademark-search-report.payment', $reportRequest));

        $this->assertDatabaseHas(TrademarkSearchReportRequest::class, [
            'email' => 'alex@example.co.uk',
            'brand_name' => 'North Pine',
            'amount' => 149.00,
            'payment_status' => 'pending',
            'report_status' => 'awaiting_payment',
        ]);
        $this->assertDatabaseMissing(ContactMessage::class, [
            'email' => 'alex@example.co.uk',
            'service_interested' => 'Trademark Search Report',
        ]);
    }

    public function test_admin_can_edit_about_sections_images_and_footer_regulatory_content(): void
    {
        Storage::fake('public');
        $admin = Admin::create([
            'name' => 'Content Admin',
            'email' => 'content-admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $payload = collect(config('about_page'))
            ->except(['hero_image', 'founder_image'])
            ->all();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.cms-pages.edit', CmsPage::ABOUT))
            ->assertOk()
            ->assertSee('Edit About Us')
            ->assertSee('Founder photograph')
            ->assertSee('Preview Page')
            ->assertSee('data-about-preview', false)
            ->assertSee('data-about-preview-modal', false)
            ->assertDontSee('formtarget="_blank"', false);

        $originalContent = CmsPage::query()->where('key', CmsPage::ABOUT)->firstOrFail()->content;
        $previewPayload = $payload;
        $previewPayload['hero_title'] = 'Unsaved preview heading.';
        $previewPayload['founder_image_upload'] = UploadedFile::fake()->image('preview-founder.png', 800, 1000);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.cms-pages.about.preview'), $previewPayload)
            ->assertOk()
            ->assertSee('Preview mode')
            ->assertSee('These changes have not been saved.')
            ->assertSee('Unsaved preview heading.')
            ->assertSee('data:image/png;base64,', false);

        $this->assertSame($originalContent, CmsPage::query()->where('key', CmsPage::ABOUT)->firstOrFail()->content);

        $payload['hero_title'] = 'A clearer About heading.';
        $payload['founder_image_upload'] = UploadedFile::fake()->image('founder.png', 800, 1000);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.cms-pages.update', CmsPage::ABOUT), $payload)
            ->assertRedirect(route('admin.cms-pages.edit', CmsPage::ABOUT));

        $aboutPage = CmsPage::query()->where('key', CmsPage::ABOUT)->firstOrFail();
        $storedAbout = json_decode($aboutPage->content, true);
        Storage::disk('public')->assertExists($storedAbout['founder_image']);

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('A clearer About heading.');

        $this->actingAs($admin, 'admin')
            ->put(route('admin.cms-pages.update', CmsPage::REGULATORY), [
                'title' => 'Updated regulatory heading',
                'content' => '<p>Updated regulatory footer copy.</p>',
            ])
            ->assertRedirect(route('admin.cms-pages.edit', CmsPage::REGULATORY));

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('Updated regulatory heading')
            ->assertSee('Updated regulatory footer copy.');
    }

    public function test_contact_form_rejects_invalid_submissions(): void
    {
        $this->from(route('contact'))
            ->post(route('contact.submit'), [
                'name' => '',
                'email' => 'not-an-email',
                'phone' => '',
                'service_interested' => 'Not a real service',
                'message' => 'short',
            ])
            ->assertRedirect(route('contact'))
            ->assertSessionHasErrors(['name', 'email', 'phone', 'service_interested', 'message']);

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_admin_can_create_update_and_delete_an_faq(): void
    {
        $admin = Admin::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.faqs.store'), [
                'question' => 'Can an admin manage this?',
                'answer' => 'Yes, from the protected admin panel.',
                'category' => 'General',
                'sort_order' => 15,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $faq = Faq::where('question', 'Can an admin manage this?')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.faqs.update', $faq), [
                'question' => 'Can an admin publish this?',
                'answer' => 'Yes, and changes appear on the public page.',
                'category' => 'Support',
                'sort_order' => 25,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.faqs.edit', $faq));

        $this->assertDatabaseHas('faqs', [
            'id' => $faq->id,
            'question' => 'Can an admin publish this?',
            'category' => 'UK Trade Marks',
        ]);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.faqs.destroy', $faq))
            ->assertRedirect(route('admin.faqs.index'));

        $this->assertDatabaseMissing('faqs', ['id' => $faq->id]);
    }

    public function test_admin_can_open_and_resolve_a_contact_message(): void
    {
        $admin = Admin::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $message = ContactMessage::create([
            'name' => 'Asha Sharma',
            'email' => 'asha@example.com',
            'phone' => '+91 99999 99999',
            'business_name' => 'Asha Brands',
            'service_interested' => 'Opposition Management',
            'subject' => 'Opposition Management',
            'message' => 'Please help with my application status.',
            'status' => 'new',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.contact-messages.show', $message))
            ->assertOk()
            ->assertSee('Asha Brands')
            ->assertSee('Opposition Management');

        $this->assertNotNull($message->fresh()->read_at);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.contact-messages.update', $message), [
                'status' => 'resolved',
                'internal_notes' => 'Called the client and requested the opposition notice.',
            ])
            ->assertRedirect(route('admin.contact-messages.show', $message));

        $this->assertDatabaseHas('contact_messages', [
            'id' => $message->id,
            'status' => 'resolved',
            'internal_notes' => 'Called the client and requested the opposition notice.',
        ]);
    }
}
