<?php

namespace App\Http\Controllers;

use App\Models\DiscountCoupon;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class AdminDiscountCouponController extends Controller
{
    public function index(Request $request): View
    {
        $coupons = DiscountCoupon::query()
            ->when($request->filled('status'), function ($query) use ($request) {
                $status = (string) $request->string('status');
                $now = now(config('app.timezone', 'Europe/London'));

                match ($status) {
                    'active' => $query->where('is_active', true)
                        ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                        ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now)),
                    'scheduled' => $query->where('is_active', true)->where('starts_at', '>', $now),
                    'expired' => $query->whereNotNull('ends_at')->where('ends_at', '<=', $now),
                    'inactive' => $query->where('is_active', false)
                        ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now)),
                    default => null,
                };
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = (string) $request->string('search');

                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.discount-coupons.index', compact('coupons'));
    }

    public function create(): View
    {
        return view('admin.discount-coupons.create', [
            'coupon' => new DiscountCoupon([
                'discount_type' => 'percentage',
                'applies_to' => 'all_services',
                'applicable_users' => 'all_users',
                'per_user_limit' => 1,
                'show_on_website' => true,
                'is_active' => true,
            ]),
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
            'selectedUserIds' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $coupon = DiscountCoupon::create($this->validatedData($request));

        return redirect()
            ->route('admin.discount-coupons.edit', $coupon)
            ->with('success', 'Discount coupon created successfully.');
    }

    public function edit(DiscountCoupon $coupon): View
    {
        return view('admin.discount-coupons.edit', [
            'coupon' => $coupon,
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
            'selectedUserIds' => collect($coupon->selected_user_ids ?? [])->map(fn ($id) => (string) $id)->all(),
        ]);
    }

    public function update(Request $request, DiscountCoupon $coupon): RedirectResponse
    {
        $coupon->update($this->validatedData($request, $coupon));

        return redirect()
            ->route('admin.discount-coupons.edit', $coupon)
            ->with('success', 'Discount coupon updated successfully.');
    }

    private function validatedData(Request $request, ?DiscountCoupon $coupon = null): array
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('discount_coupons', 'code')->ignore($coupon),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'discount_type' => ['required', Rule::in(['percentage', 'flat'])],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'applies_to' => ['required', Rule::in([
                'all_services',
                'trademark_filing',
                'uk_search',
                'uk_examination_response',
            ])],
            'applicable_users' => ['required', Rule::in(['all_users', 'specific_users'])],
            'selected_user_ids' => ['nullable', 'array'],
            'selected_user_ids.*' => ['integer', 'exists:users,id'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'auto_apply' => ['nullable', 'boolean'],
            'show_on_website' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['code'] = strtoupper(trim($data['code']));
        $data['selected_user_ids'] = $data['applicable_users'] === 'specific_users'
            ? collect($data['selected_user_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all()
            : null;
        $data['auto_apply'] = $request->boolean('auto_apply');
        if (in_array($data['applies_to'], ['trademark_filing', 'all_services'], true)) {
            $data['auto_apply'] = true;
        }
        $data['stackable'] = false;
        $data['show_on_website'] = $request->boolean('show_on_website');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
