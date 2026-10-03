<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\AdminNavigation;
use App\Support\AdminStatusPalette;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_admin_pages_use_the_grouped_sidebar_layout(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('admin-topbar', false)
            ->assertSee('admin-sidebar', false)
            ->assertSee('admin-layout-shell', false)
            ->assertSee('admin-layout-main', false)
            ->assertSee('transform: translateX(-105%)', false)
            ->assertSee('body.admin-sidebar-open', false)
            ->assertSee('Trademarks')
            ->assertDontSee('Opposition &amp; Objections', false)
            ->assertDontSee('Recovery Cases')
            ->assertSee('Website')
            ->assertSee('Inbox')
            ->assertSee('Quick links')
            ->assertSee('All Applications')
            ->assertSee('Career Applications')
            ->assertSee('Registered Users')
            ->assertSee('View registered users')
            ->assertSee('Published reviews')
            ->assertSee('Manage reviews')
            ->assertSee('Log out')
            ->assertSee('action="'.route('admin.logout').'"', false)
            ->assertSee(route('admin.blogs.index'), false)
            ->assertSee(route('admin.contact-messages.index'), false)
            ->assertSee(route('admin.reviews.index'), false)
            ->assertSee(route('admin.users.index'), false)
            ->assertSee(route('admin.discount-coupons.index'), false);
    }

    public function test_trademark_application_listing_contains_status_summaries(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.all-applications'))
            ->assertOk()
            ->assertSee('Trademark Applications')
            ->assertSee('admin-list-table', false)
            ->assertSee('Pending Review')
            ->assertSee('Approved')
            ->assertSee('Filed')
            ->assertSee('Registered');
    }

    public function test_navigation_survives_a_stale_live_configuration_cache(): void
    {
        Config::set('admin_navigation', [
            ['label' => 'Stale navigation', 'items' => []],
        ]);

        $groups = AdminNavigation::groups();

        $this->assertNotEmpty($groups);
        $this->assertSame('Overview', $groups[0]['label']);
        $this->assertTrue(collect($groups)->pluck('items')->flatten(1)->contains('route', 'admin.reviews.index'));
        $this->assertTrue(collect($groups)->pluck('items')->flatten(1)->contains('route', 'admin.users.index'));

        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('All Applications')
            ->assertSee('Career Applications')
            ->assertSee('Quick links');
    }

    public function test_every_admin_listing_has_working_search_and_status_filters(): void
    {
        $admin = $this->createAdmin();
        $routes = [
            'admin.applications' => 'UNDER_REVIEW',
            'admin.all-applications' => 'UNDER_REVIEW',
            'admin.blogs.index' => 'draft',
            'admin.career-jobs.index' => 'open',
            'admin.career-applications.index' => 'new',
            'admin.contact-messages.index' => 'new',
            'admin.faqs.index' => 'published',
            'admin.reviews.index' => 'published',
            'admin.users.index' => 'verified',
            'admin.discount-coupons.index' => 'active',
        ];

        foreach ($routes as $routeName => $status) {
            $this->actingAs($admin, 'admin')
                ->get(route($routeName, ['search' => 'no-matching-record', 'status' => $status]))
                ->assertOk()
                ->assertSee('name="search"', false)
                ->assertSee('name="status"', false);
        }
    }

    public function test_status_palette_is_stable_for_the_same_status_and_distinct_for_different_statuses(): void
    {
        $this->assertSame(
            AdminStatusPalette::style('IN_PROGRESS'),
            AdminStatusPalette::style('In Progress')
        );
        $this->assertNotSame(
            AdminStatusPalette::style('In Progress'),
            AdminStatusPalette::style('Pending Review')
        );
        $this->assertSame('In progress', AdminStatusPalette::schemeLabel('Under Review'));
        $this->assertSame('Pending / waiting', AdminStatusPalette::schemeLabel('Payment Pending'));
        $this->assertSame('Successful / active', AdminStatusPalette::schemeLabel('Published'));
        $this->assertSame('Blocked / unsuccessful', AdminStatusPalette::schemeLabel('Rejected'));
    }

    private function createAdmin(): Admin
    {
        return Admin::create([
            'name' => 'Navigation Admin',
            'email' => 'navigation-admin@example.com',
            'password' => bcrypt('password'),
        ]);
    }
}
