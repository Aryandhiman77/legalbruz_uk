<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\WebsiteVisitor;
use App\Models\WebsiteServiceVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteVisitorTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_views_create_one_unique_website_visitor(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertCookie('legal_bruz_visitor');

        $this->get(route('faq'))->assertOk();

        $this->assertDatabaseCount('website_visitors', 1);
        $this->assertSame(2, WebsiteVisitor::firstOrFail()->page_views);
    }

    public function test_opening_a_service_marks_the_unique_visitor_as_a_service_lead(): void
    {
        $this->get(route('landing'))->assertOk();
        $this->get(route('trademark.search-page'))->assertOk();

        $visitor = WebsiteVisitor::firstOrFail();

        $this->assertNotNull($visitor->service_first_visited_at);
        $this->assertSame('trademark_registration', $visitor->first_service);
        $this->assertSame(1, WebsiteVisitor::whereNotNull('service_first_visited_at')->count());
        $this->assertDatabaseCount('website_service_visits', 1);
        $this->assertDatabaseHas('website_service_visits', [
            'service_key' => 'trademark_registration',
        ]);
    }

    public function test_repeat_views_of_one_service_increment_views_without_duplicating_its_visitor(): void
    {
        $this->get(route('trademark.search-page'))->assertOk();
        $this->get(route('trademark.search-page'))->assertOk();

        $this->assertDatabaseCount('website_service_visits', 1);
        $this->assertSame(2, WebsiteServiceVisit::firstOrFail()->page_views);
    }

    public function test_each_active_public_service_is_tracked_separately(): void
    {
        $services = [
            ['trademark.search-page', [], 'trademark_registration'],
            ['contact', ['service' => 'classification_specification'], 'classification_specification'],
            ['trademark-search-report.create', [], 'trademark_search_report'],
            ['examination-reply.landing', [], 'examination_report_reply'],
            ['trademark.opposition-management', [], 'opposition_management'],
            ['book-call.create', [], 'consultation_call'],
        ];

        foreach ($services as [$routeName, $parameters, $serviceKey]) {
            $this->get(route($routeName, $parameters))->assertOk();

            $this->assertDatabaseHas('website_service_visits', [
                'service_key' => $serviceKey,
                'page_views' => 1,
            ]);
        }

        $this->assertDatabaseCount('website_visitors', 1);
        $this->assertDatabaseCount('website_service_visits', count($services));
    }

    public function test_admin_pages_are_not_counted_and_dashboard_displays_visitor_totals(): void
    {
        $this->get(route('landing'))->assertOk();
        $this->get(route('trademark.search-page'))->assertOk();

        $admin = Admin::create([
            'name' => 'Analytics Admin',
            'email' => 'analytics-admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Website visitors')
            ->assertSee('Service leads')
            ->assertSee('Visitors by service')
            ->assertSee('UK Trade Mark Filing')
            ->assertSee('Classification &amp; Specification', false)
            ->assertSee('Trademark Search Report')
            ->assertSee('Examination Report')
            ->assertSee('Opposition Service')
            ->assertSee('Book a Call')
            ->assertSee(route('admin.contact-messages.index', ['search' => 'Consultation Call']), false)
            ->assertSee('Objection Replies')
            ->assertSee('Defence Cases')
            ->assertSee('Oppose Cases')
            ->assertDontSee('Filed &amp; Stuck Recovery', false);

        $this->assertDatabaseCount('website_visitors', 1);
    }

    public function test_opposition_and_examination_are_live_while_stuck_recovery_stays_disabled(): void
    {
        $this->get(route('stuck-trademark.landing'))->assertNotFound();
        $this->get(route('trademark.opposition-management'))->assertOk()->assertSee('OPTION A')->assertSee('OPTION B');
        $this->get(route('examination-reply.landing'))->assertOk()->assertSee('Trademark Objection Reply');
    }
}
