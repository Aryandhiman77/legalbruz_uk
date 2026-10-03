<?php

namespace App\Http\Controllers;

use App\Models\CustomerReview;
use App\Models\Faq;
use App\Models\TrademarkPricing;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $faqs = Schema::hasTable('faqs')
            ? Faq::query()->published()->where('category', 'like', 'UK%')->orderBy('sort_order')->orderBy('id')->get()
            : collect();

        return view('home-uk', [
            'trademarkPricingPlans' => TrademarkPricing::activePlans(),
            'customerReviews' => CustomerReview::homepageReviews(),
            'faqs' => $faqs,
        ]);
    }
}
