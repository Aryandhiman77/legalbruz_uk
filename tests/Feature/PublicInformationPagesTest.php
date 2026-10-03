<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CmsPage;
use App\Models\ContactMessage;
use App\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('<option value="Examination Response"', false)
            ->assertSee('info@legalbruz.com')
            ->assertDontSee('support@legalbruz.com')
            ->assertSee('34 Krishna Nagar, Ambala Cantt, Haryana -133001')
            ->assertSee('Top Floor Chamber no.98 Ambala District court, Haryana')
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

    public function test_contact_form_accepts_examination_response_as_a_service(): void
    {
        $this->post(route('contact.submit'), [
            'name' => 'Jamie Carter',
            'email' => 'jamie@example.co.uk',
            'phone' => '+44 7123 456789',
            'business_name' => 'Carter Brands Ltd',
            'service_interested' => 'Examination Response',
            'message' => 'I need help responding to a UKIPO examination report.',
        ])
            ->assertRedirect(route('contact'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas(ContactMessage::class, [
            'email' => 'jamie@example.co.uk',
            'service_interested' => 'Examination Response',
            'subject' => 'Examination Response',
        ]);
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
            ->patch(route('admin.contact-messages.update', $message), ['status' => 'resolved'])
            ->assertRedirect(route('admin.contact-messages.show', $message));

        $this->assertDatabaseHas('contact_messages', [
            'id' => $message->id,
            'status' => 'resolved',
        ]);
    }
}
