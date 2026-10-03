<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DiscountCoupon;
use App\Models\Faq;
use App\Models\TrademarkPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UkHomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders_the_complete_uk_trade_mark_experience(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('Protect Your Brand in')
            ->assertSee('UK TRADE MARK SERVICES', false)
            ->assertSee('Your UK filing journey')
            ->assertSee('Search before committing to a UK application')
            ->assertSee('Your UK Trade Mark Journey')
            ->assertSee('Clear and itemised pricing')
            ->assertSee('UK trade mark FAQs')
            ->assertSee('href="#trademark-search"', false)
            ->assertSee('id="trademark-search"', false)
            ->assertSee('Book a call')
            ->assertSee('Legal Bruz Pvt. Ltd.')
            ->assertSee('legal-bruz-pvt-ltd-logo.png', false)
            ->assertSee('£399')
            ->assertDontSee('£149')
            ->assertDontSee('₹')
            ->assertDontSee('Legal Bruz LLP')
            ->assertDontSee('Legal Bruz Private Ltd')
            ->assertSee('Copyright registration')
            ->assertSee('Patent registration')
            ->assertSee('Coming soon');
    }

    public function test_forwarded_ngrok_https_request_generates_https_asset_urls(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
        ])->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'legalbruz-test.ngrok-free.app',
            'X-Forwarded-Port' => '443',
        ])->get('/')
            ->assertOk()
            ->assertSee('href="https://legalbruz-test.ngrok-free.app/css/home-uk.css"', false)
            ->assertSee('src="https://legalbruz-test.ngrok-free.app/legal-bruz-pvt-ltd-logo.png"', false)
            ->assertDontSee('http://localhost/css/', false);
    }

    public function test_trademark_search_header_uses_the_constrained_company_logo(): void
    {
        $this->get(route('trademark.search-page'))
            ->assertOk()
            ->assertSee('legal-bruz-pvt-ltd-logo.png', false)
            ->assertSee('class="navbar-logo" width="106" height="79"', false)
            ->assertDontSee('sizes="(max-width: 768px) 100vw, 50px"', false);
    }

    public function test_trade_mark_journey_uses_the_eight_step_snake_layout(): void
    {
        $content = $this->get(route('landing'))->assertOk()->getContent();

        $this->assertSame(8, substr_count($content, 'class="process-card reveal"'));
        $this->assertSame(7, substr_count($content, 'class="journey-arrow '));
        $this->assertStringContainsString('class="journey-route"', $content);
        $this->assertStringContainsString('class="process-skyline"', $content);
        $this->assertStringContainsString('class="process-waves"', $content);
        $this->assertStringContainsString('Your idea<br>to a stronger<br>tomorrow', $content);
        $this->assertStringContainsString('Eight simple<br>steps to protection', $content);
        $this->assertStringContainsString('Registration<br>is within reach', $content);

        $this->assertLessThan(strpos($content, '>08</div>'), strpos($content, '>04</div>'));
        $this->assertLessThan(strpos($content, '>07</div>'), strpos($content, '>08</div>'));
        $this->assertLessThan(strpos($content, '>06</div>'), strpos($content, '>07</div>'));
        $this->assertLessThan(strpos($content, '>05</div>'), strpos($content, '>06</div>'));
    }

    public function test_admin_can_update_the_public_uk_application_price_in_pounds(): void
    {
        $admin = Admin::create([
            'name' => 'UK Pricing Admin',
            'email' => 'uk-pricing@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.trademark-pricing.update'), [
                'prices' => [
                    TrademarkPricing::APPLICATION => 425,
                ],
            ])
            ->assertRedirect(route('admin.trademark-pricing.edit'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('trademark_pricings', ['key' => TrademarkPricing::APPLICATION, 'amount' => 425]);

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('£425')
            ->assertDontSee('£149')
            ->assertDontSee('£249');

        $this->assertSame(425.0, TrademarkPricing::amountForApplicantType('individual'));
        $this->assertSame(425.0, TrademarkPricing::amountForApplicantType('company'));
    }

    public function test_first_visit_disclaimer_is_present_on_the_uk_homepage(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('id="lb-disclaimer-modal"', false)
            ->assertSee('id="lb-disclaimer-checkbox"', false)
            ->assertSee("const consentKey = 'legalbruz_disclaimer_consent_v1';", false)
            ->assertSee("if (!modal || getConsent() === 'accepted') return;", false)
            ->assertSee('modal.hidden = false;', false);
    }

    public function test_homepage_accordion_uses_published_uk_faqs_only(): void
    {
        Faq::create([
            'question' => 'A UK filing question?',
            'answer' => 'A UK-focused answer.',
            'category' => 'UK Trade Marks',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        Faq::create([
            'question' => 'An India-only question?',
            'answer' => 'This should not appear on the UK homepage.',
            'category' => 'India Trade Marks',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('A UK filing question?')
            ->assertDontSee('An India-only question?');
    }

    public function test_homepage_hides_the_faq_section_and_links_when_no_uk_faqs_exist(): void
    {
        Faq::query()->delete();

        $this->get(route('landing'))
            ->assertOk()
            ->assertDontSee('UK trade mark FAQs')
            ->assertDontSee('href="#faqs"', false)
            ->assertSee('href="' . route('faq') . '"', false);
    }

    public function test_homepage_shows_only_the_first_six_uk_faqs_by_sort_order(): void
    {
        Faq::query()->delete();

        foreach ([70, 20, 60, 10, 50, 30, 40] as $sortOrder) {
            Faq::create([
                'question' => "Sorted FAQ {$sortOrder}?",
                'answer' => "Answer {$sortOrder}.",
                'category' => 'UK Trade Marks',
                'sort_order' => $sortOrder,
                'is_active' => true,
            ]);
        }

        $response = $this->get(route('landing'))->assertOk();

        foreach ([10, 20, 30, 40, 50, 60] as $sortOrder) {
            $response->assertSee("Sorted FAQ {$sortOrder}?");
        }

        $response
            ->assertDontSee('Sorted FAQ 70?')
            ->assertSeeInOrder([
                'Sorted FAQ 10?',
                'Sorted FAQ 20?',
                'Sorted FAQ 30?',
                'Sorted FAQ 40?',
                'Sorted FAQ 50?',
                'Sorted FAQ 60?',
            ]);
    }

    public function test_flat_uk_coupon_uses_pounds_and_updates_the_public_price(): void
    {
        DiscountCoupon::create([
            'code' => 'UKSAVE25',
            'title' => 'UK filing offer',
            'discount_type' => 'flat',
            'discount_value' => 25,
            'applies_to' => 'trademark_filing',
            'applicable_users' => 'all_users',
            'per_user_limit' => 1,
            'auto_apply' => true,
            'stackable' => false,
            'show_on_website' => true,
            'is_active' => true,
        ]);

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('£399')
            ->assertSee('£374')
            ->assertSee('UKSAVE25')
            ->assertSee('£25.00')
            ->assertDontSee('₹');
    }
}
