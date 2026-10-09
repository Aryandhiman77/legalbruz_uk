<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DiscountCoupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountCouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_coupon_form_has_no_auto_apply_option_and_backend_always_enables_it(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Coupon Admin',
            'email' => 'coupon-admin@example.test',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.discount-coupons.create'))
            ->assertOk()
            ->assertDontSee('Auto Apply')
            ->assertDontSee('name="auto_apply"', false);

        $this->post(route('admin.discount-coupons.store'), [
            'code' => 'ALWAYS25',
            'title' => 'Automatic offer',
            'discount_type' => 'percentage',
            'discount_value' => 25,
            'applies_to' => 'uk_search',
            'applicable_users' => 'all_users',
            'auto_apply' => 0,
            'show_on_website' => 1,
            'is_active' => 1,
        ])->assertRedirect();

        $coupon = DiscountCoupon::query()->where('code', 'ALWAYS25')->firstOrFail();

        $this->assertTrue($coupon->auto_apply);

        $this->get(route('admin.discount-coupons.edit', $coupon))
            ->assertOk()
            ->assertDontSee('Auto Apply')
            ->assertDontSee('name="auto_apply"', false);
    }
}
