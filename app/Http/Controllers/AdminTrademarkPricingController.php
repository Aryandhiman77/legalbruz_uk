<?php

namespace App\Http\Controllers;

use App\Models\TrademarkPricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTrademarkPricingController extends Controller
{
    public function edit(): View
    {
        return view('admin.trademark-pricing.edit', [
            'plans' => TrademarkPricing::allEditablePlans(),
            'defaults' => TrademarkPricing::defaults(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prices' => ['required', 'array'],
            'prices.uk_application' => ['required', 'numeric', 'min:1', 'max:1000000'],
        ]);

        TrademarkPricing::ensureDefaults();

        foreach ([TrademarkPricing::APPLICATION] as $key) {
            $default = TrademarkPricing::defaults()[$key];
            TrademarkPricing::query()->updateOrCreate(
                ['key' => $key],
                [
                    'label' => $default['label'],
                    'amount' => round((float) $validated['prices'][$key], 2),
                    'is_active' => true,
                    'sort_order' => $default['sort_order'],
                ],
            );
        }

        TrademarkPricing::flushCache();

        return redirect()
            ->route('admin.trademark-pricing.edit')
            ->with('success', 'UK trade mark pricing updated across the website.');
    }
}
