<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>UK Trade Mark Services | Legal Bruz</title>
    <meta name="description" content="UK trade mark searches, application preparation and filing support for businesses, founders and brand owners.">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <link rel="canonical" href="{{ route('landing') }}">
    <link rel="icon" type="image/png" href="{{ asset('legal-bruz-ltd-logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@500;600&family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/home-uk.css') }}">
    <link rel="stylesheet" href="{{ asset('css/site-footer.css') }}?v={{ filemtime(public_path('css/site-footer.css')) }}">
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Legal Bruz Ltd.',
        'url' => route('landing'),
        'logo' => asset('legal-bruz-ltd-logo.png'),
        'sameAs' => collect(config('social_links'))->pluck('url')->values()->all(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
</head>
<body>
    @php
        $plans = $trademarkPricingPlans ?? \App\Models\TrademarkPricing::activePlans();
        $defaults = \App\Models\TrademarkPricing::defaults();
        $applicationHref = auth()->check() ? route('trademark.type-selection') : route('register');
        $applicationPrice = (float) ($plans['uk_application']['amount'] ?? $defaults['uk_application']['amount']);
        $applicationCoupon = auth()->check()
            ? \App\Models\DiscountCoupon::autoApplyForPayment('trademark_filing', auth()->id())
            : \App\Models\DiscountCoupon::autoApplyForPublicService('trademark_filing');
        $discountedApplicationPrice = $applicationCoupon
            ? $applicationCoupon->discountedAmountFor($applicationPrice)
            : $applicationPrice;
        $applicationPriceDetails = [
            'original' => $applicationPrice,
            'current' => $discountedApplicationPrice,
            'coupon' => $discountedApplicationPrice < $applicationPrice ? $applicationCoupon : null,
        ];
        $couponBannerEndsAt = $applicationPriceDetails['coupon']?->ends_at
            ? $applicationPriceDetails['coupon']->ends_at
                ->copy()
                ->timezone(config('app.timezone', 'Europe/London'))
                ->toIso8601String()
            : null;
        $displayFaqs = $faqs ?? collect();
    @endphp

    <a class="skip-link" href="#main-content">Skip to main content</a>

    @if ($applicationPriceDetails['coupon'])
        <aside
            class="coupon-banner"
            aria-label="Active discount offer"
            data-coupon-banner
            @if ($couponBannerEndsAt) data-coupon-ends-at="{{ $couponBannerEndsAt }}" @endif
        >
            <div class="page-shell coupon-banner-inner">
                <div class="coupon-banner-copy">
                    <span class="coupon-banner-label">Active offer</span>
                    <strong>{{ $applicationPriceDetails['coupon']->code }}</strong>
                    <span>
                        {{ $applicationPriceDetails['coupon']->discount_label }} on UK Trade Mark Application
                    </span>
                    @if ($couponBannerEndsAt)
                        <span class="coupon-banner-countdown">
                            Ends in <b data-coupon-countdown>Calculating…</b>
                        </span>
                    @else
                        <span class="coupon-banner-countdown">Automatically applied at checkout</span>
                    @endif
                </div>
                <a href="{{ $applicationHref }}" class="coupon-banner-action">Claim offer <span aria-hidden="true">→</span></a>
            </div>
        </aside>
    @endif

    <header class="site-header" data-header>
        <div class="page-shell nav-shell">
            <a class="brand" href="{{ route('landing') }}" aria-label="Legal Bruz home">
                <img src="{{ asset('legal-bruz-ltd-logo.png') }}" alt="Legal Bruz Ltd." width="106" height="79">
            </a>
            <button class="nav-toggle" type="button" aria-label="Open navigation" aria-expanded="false" data-nav-toggle>
                <span></span><span></span><span></span>
            </button>
            <nav class="main-nav" aria-label="Main navigation" data-nav>
                <a href="#services">Services</a>
                <a href="#process">How it works</a>
                <a href="#pricing">Pricing</a>
                @if ($displayFaqs->isNotEmpty())
                    <a href="#faqs">FAQs</a>
                @endif
                <a href="{{ route('contact') }}">Contact</a>
            </nav>
            <div class="nav-actions">
                @auth
                    <a class="nav-login" href="{{ route('dashboard') }}">Client dashboard</a>
                @else
                    <a class="nav-login" href="{{ route('login') }}">Client login</a>
                @endauth
                <a class="button button-small" href="{{ $applicationHref }}">Start application</a>
            </div>
        </div>
    </header>

    <main id="main-content">
        <section class="hero-section">
            <div class="hero-orb hero-orb-one"></div>
            <div class="hero-orb hero-orb-two"></div>
            <div class="page-shell hero-grid">
                <div class="hero-copy reveal">
                    <p class="eyebrow"><span></span> UK TRADE MARK SERVICES</p>
                    <h1>Protect Your Brand in <em>the UK</em></h1>
                    <p class="hero-lead">UK trade mark searches, application preparation and filing support for businesses, founders and brand owners.</p>
                    <div class="hero-actions">
                        <a class="button" href="{{ $applicationHref }}">Start your application <span aria-hidden="true">→</span></a>
                        <a class="button button-secondary" href="https://legalbruz.com" target="_blank" rel="noopener noreferrer">Visit Legalbruz India</a>
                    </div>
                    <p class="assurance"><span>Clear pricing</span><i></i><span>Human review</span><i></i><span>Client approval before filing</span></p>
                    <p class="hero-disclaimer">UKIPO official fees are charged separately unless expressly included. Filing does not guarantee registration.</p>
                </div>

                <div class="filing-card-wrap reveal reveal-delay">
                    <div class="filing-card-glow"></div>
                    <article class="filing-card" aria-labelledby="filing-card-title">
                        <div class="filing-card-topline">
                            <span class="live-dot"></span>
                            <span>A clear, guided process</span>
                            <span class="secure-label">Secure</span>
                        </div>
                        <h2 id="filing-card-title">Your UK filing journey</h2>
                        <ol class="filing-steps">
                            <li><span>01</span><div><strong>Tell us about your brand</strong><small>Share the mark, owner and business details.</small></div></li>
                            <li><span>02</span><div><strong>Review classes and terms</strong><small>We prepare the goods and services wording.</small></div></li>
                            <li><span>03</span><div><strong>Approve the application</strong><small>Nothing is filed without your sign-off.</small></div></li>
                            <li><span>04</span><div><strong>File and track online</strong><small>Follow UKIPO progress from your dashboard.</small></div></li>
                        </ol>
                        <div class="filing-card-footer">
                            <div><span class="avatar-stack"><b>LB</b><b>UK</b></span><span>Human-reviewed filing</span></div>
                            <span class="status-chip">Ready when you are</span>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="proof-strip" aria-label="Service commitments">
            <div class="page-shell proof-grid">
                @foreach ([
                    ['UK-focused service', 'Prepared for the UKIPO'],
                    ['Clear fee breakdown', 'No hidden official fees'],
                    ['Client approval', 'Nothing filed without approval'],
                    ['Online tracking', 'Documents and progress online'],
                ] as [$title, $text])
                    <article class="proof-item reveal">
                        <span class="check-icon">✓</span>
                        <div><strong>{{ $title }}</strong><small>{{ $text }}</small></div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="section services-section" id="services">
            <div class="page-shell">
                <div class="section-heading reveal">
                    <p class="eyebrow centered"><span></span> Focused support <span></span></p>
                    <h2>UK Trade Mark Services</h2>
                    <p>Practical support for protecting and managing your brand in the United Kingdom.</p>
                </div>
                <div class="services-grid">
                    @foreach ([
                        ['search', 'Trademark Search Report', 'UK register search, class review and written risk observations.', 'Request a search report', route('trademark-search-report.create'), 'Available now'],
                        ['application', 'UK Trade Mark Application', 'Owner review, specification preparation, filing and tracking.', 'Start an application', $applicationHref, 'Open for filing'],
                        ['classes', 'Classification & Specification', 'Class recommendations and carefully drafted goods and services.', 'Get support', route('contact', ['service' => 'classification_specification']), 'Included with filing'],
                        ['response', 'Examination Report', 'Support with examination reports, objections and reply filing.', 'Start with examination report', route('examination-reply.landing'), 'Available now'],
                        ['monitor', 'Opposition Service', 'Choose Flow A to defend your mark or Flow B to oppose a conflicting mark.', 'Choose an opposition flow', route('trademark.opposition-management'), 'Available now'],
                        ['renewal', 'Trade Mark Renewal', 'Renewal review, submission and confirmation.', 'Coming soon', null, 'Planned service'],
                    ] as $index => [$icon, $title, $text, $action, $href, $tag])
                        <article class="service-card {{ $index === 1 ? 'service-card-featured' : '' }} reveal">
                            <div class="service-card-head"><span class="service-icon service-icon-{{ $icon }}" aria-hidden="true"></span><span class="service-tag">{{ $tag }}</span></div>
                            <h3>{{ $title }}</h3>
                            <p>{{ $text }}</p>
                            @if ($href)
                                <a href="{{ $href }}">{{ $action }} <span aria-hidden="true">↗</span></a>
                            @else
                                <span class="muted-action">{{ $action }}</span>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        @if (config('uk_site.trademark_search_widget_enabled'))
        <section class="search-section" id="trademark-search">
            <div class="page-shell search-panel reveal">
                <div class="search-decoration" aria-hidden="true"></div>
                <div class="search-copy">
                    <p class="eyebrow eyebrow-light"><span></span> Check before you file</p>
                    <h2>Search before committing to a UK application</h2>
                    <p>A similar earlier trade mark may increase the risk of an objection, notification or opposition.</p>
                    <ul><li>Search matching UK register records</li><li>Review names before investing in a filing</li></ul>
                </div>
                <form class="search-form" action="{{ route('trademark.search-page') }}" method="GET">
                    <label for="uk-trademark-keyword">Brand or trade mark name</label>
                    <div class="search-field">
                        <span aria-hidden="true"></span>
                        <input id="uk-trademark-keyword" name="keyword" type="search" minlength="2" placeholder="e.g. North &amp; Pine" required>
                        <button type="submit">Search UK trade marks <span aria-hidden="true">→</span></button>
                    </div>
                    <p>Preliminary search only — not a complete legal clearance opinion.</p>
                </form>
            </div>
        </section>
        @endif

        <section class="process-section" id="process">
            <div class="process-corner process-corner-top" aria-hidden="true"></div>
            <div class="process-note process-note-left" aria-hidden="true">
                <span>Your idea<br>to a stronger<br>tomorrow</span>
                <svg viewBox="0 0 55 42"><path d="M3 3c17 4 34 12 45 30m0 0-2-13m2 13-12-4"/></svg>
            </div>
            <div class="process-note process-note-right" aria-hidden="true">
                <span>Eight simple<br>steps to protection</span>
                <svg viewBox="0 0 52 47"><path d="M41 3c1 17-5 29-29 38m0 0 7-12m-7 12 13 1"/></svg>
            </div>

            <div class="page-shell process-shell">
                <div class="section-heading process-heading reveal">
                    <p class="eyebrow centered"><span></span> From details to filing <span></span></p>
                    <h2>Your UK Trade Mark Journey</h2>
                    <p>A clear process from first details to registration tracking.</p>
                </div>

                <div class="journey-flow">
                    <svg class="journey-route" viewBox="0 0 1180 552" preserveAspectRatio="none" aria-hidden="true">
                        <path d="M18 120H1150c17 0 30 14 30 31v250c0 17-13 31-30 31H30c-17 0-30-14-30-31V151c0-17 13-31 30-31"/>
                    </svg>
                    <span class="journey-arrow journey-arrow-top journey-arrow-one" aria-hidden="true">→</span>
                    <span class="journey-arrow journey-arrow-top journey-arrow-two" aria-hidden="true">→</span>
                    <span class="journey-arrow journey-arrow-top journey-arrow-three" aria-hidden="true">→</span>
                    <span class="journey-arrow journey-arrow-turn" aria-hidden="true">↓</span>
                    <span class="journey-arrow journey-arrow-bottom journey-arrow-four" aria-hidden="true">←</span>
                    <span class="journey-arrow journey-arrow-bottom journey-arrow-five" aria-hidden="true">←</span>
                    <span class="journey-arrow journey-arrow-bottom journey-arrow-six" aria-hidden="true">←</span>

                    <div class="process-grid">
                    @foreach ([
                        ['01', 'account', 'Create account', 'Register and provide contact details.'],
                        ['02', 'brand', 'Brand details', 'Tell us about the mark, owner and business.'],
                        ['03', 'search', 'Search & review', 'Check conflicts and identify filing risks.'],
                        ['04', 'tag', 'Specification', 'Prepare classes and goods or services.'],
                        ['08', 'progress', 'Track progress', 'Follow examination through to registration.'],
                        ['07', 'file', 'File with UKIPO', 'Submit after payment and final checks.'],
                        ['06', 'sign', 'Approve & sign', 'Approve details and give filing authority.'],
                        ['05', 'review', 'Final review', 'See the complete filing summary.'],
                    ] as [$number, $icon, $title, $text])
                        <article class="process-card reveal">
                            <div class="process-number">{{ $number }}</div>
                            <div class="process-icon" aria-hidden="true">
                                @switch($icon)
                                    @case('account')
                                        <svg viewBox="0 0 48 48"><circle cx="24" cy="15" r="8"/><path d="M10 39v-3c0-8 6-12 14-12s14 4 14 12v3Z"/></svg>
                                        @break
                                    @case('brand')
                                        <svg viewBox="0 0 48 48"><path d="M14 6h14l9 9v27H14Z"/><path d="M28 6v10h9M20 25h11M20 32h11"/></svg>
                                        @break
                                    @case('search')
                                        <svg viewBox="0 0 48 48"><circle cx="21" cy="21" r="13"/><path d="m31 31 10 10"/></svg>
                                        @break
                                    @case('tag')
                                        <svg viewBox="0 0 48 48"><path d="m7 25 18-18h14l2 2v14L23 41Z"/><circle cx="34" cy="14" r="2"/></svg>
                                        @break
                                    @case('review')
                                        <svg viewBox="0 0 48 48"><path d="m10 13 3 3 5-7M23 13h15M10 25l3 3 5-7M23 25h15M10 37l3 3 5-7M23 37h15"/></svg>
                                        @break
                                    @case('sign')
                                        <svg viewBox="0 0 48 48"><path d="M8 37c6-3 7-8 11-17 3-7 7-13 10-12 5 2-2 14-7 20-3 4-5 7-3 8 3 1 7-8 10-8 2 0-1 7 2 8 2 1 4-4 6-4 2 1 1 4 4 4"/><path d="M7 41h34"/></svg>
                                        @break
                                    @case('file')
                                        <svg viewBox="0 0 48 48"><path d="M12 25v14c0 2 2 4 4 4h20c2 0 4-2 4-4V25M26 34V5m0 0-9 9m9-9 9 9"/></svg>
                                        @break
                                    @case('progress')
                                        <svg viewBox="0 0 48 48"><path d="M9 39h7V29H9Zm12 0h7V20h-7Zm12 0h7V8h-7Z"/></svg>
                                        @break
                                @endswitch
                            </div>
                            <h3>{{ $title }}</h3>
                            <p>{{ $text }}</p>
                        </article>
                    @endforeach
                    </div>
                </div>

                <div class="process-action reveal">
                    <span>Ready to take the first step?</span>
                    <a class="button" href="{{ $applicationHref }}">Start your application <span>→</span></a>
                </div>
            </div>

            <div class="process-note process-note-bottom" aria-hidden="true">
                <svg viewBox="0 0 58 35"><path d="M55 30C38 29 22 21 8 7m0 0 3 13M8 7l14 3"/></svg>
                <span>Registration<br>is within reach</span>
            </div>
            <svg class="process-skyline" viewBox="0 0 560 150" aria-hidden="true">
                <path d="M0 150v-45h13v-20h6v20h12V79h5v26h12V91h6v14h17V75h6v30h15v-18h7v18h15V58h6V38h4V18l4-18 4 18v20h4v20h7v47h28V92h13v13h28V89h10v16h31V83h12v22h28V84h9v21h24V72h8v33h30V85h8v20h37v45Z"/>
                <path d="M205 150v-20h24l18-26 18 26h20v20m-46-20h18m8 0h19M246 104v46M0 146h560"/>
            </svg>
            <svg class="process-waves" viewBox="0 0 1440 128" preserveAspectRatio="none" aria-hidden="true">
                <path d="M0 63c114-45 181 15 273 18 111 3 142-31 240-15 87 14 116 48 222 36 99-11 130-55 226-45 84 9 118 47 205 38 107-10 148-71 274-68v101H0Z"/>
                <path d="M0 91c98-31 166 16 255 17 98 1 144-26 235-9 91 17 139 32 225 20 109-15 154-51 254-32 93 18 123 37 213 22 104-17 158-47 258-35v54H0Z"/>
            </svg>
        </section>

        <section class="section benefits-section" id="why-us">
            <div class="page-shell benefits-layout">
                <div class="benefits-intro reveal">
                    <p class="eyebrow"><span></span> Why Legal Bruz</p>
                    <h2>A clearer way to file a UK trade mark</h2>
                    <p>Factual support, careful preparation and visibility at each step — without unsupported success claims.</p>
                    <div class="benefits-quote"><span>“</span><p>You see what is being prepared and approve it before anything is submitted.</p></div>
                </div>
                <div class="benefits-grid">
                    @foreach ([
                        ['Human review', 'Applications are reviewed before submission.'],
                        ['Clear pricing', 'Professional and official fees are separated.'],
                        ['Prepared specifications', 'Wording reflects real and genuinely planned activities.'],
                        ['Client approval', 'Nothing is filed before final approval.'],
                        ['Online tracking', 'Access documents and progress in one place.'],
                        ['UK-focused work', 'A filing process prepared around UKIPO requirements.'],
                    ] as [$title, $text])
                        <article class="benefit-card reveal"><span>✓</span><div><h3>{{ $title }}</h3><p>{{ $text }}</p></div></article>
                    @endforeach
                </div>
            </div>
            @if (isset($customerReviews) && $customerReviews->isNotEmpty())
                <div class="page-shell review-ribbon reveal" data-testimonials-carousel aria-label="Customer reviews">
                    <div class="review-ribbon-title"><span>Client experiences</span><strong>What brand owners say</strong></div>
                    <div class="review-carousel" data-review-carousel>
                        <div class="review-ribbon-track" data-review-track tabindex="0" aria-label="Client review pages">
                            @foreach ($customerReviews as $review)
                                <blockquote data-review-card>
                                    <div class="review-stars" aria-label="{{ $review->rating }} out of 5 stars">
                                        <span aria-hidden="true">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                                    </div>
                                    <p>“{{ $review->review }}”</p>
                                    <footer>
                                        <span class="review-logo {{ $review->logo_path ? 'has-image' : '' }}" aria-hidden="true">
                                            @if ($review->logo_path)
                                                <img src="{{ route('storage.public.view', ['path' => $review->logo_path]) }}" alt="" loading="lazy">
                                            @else
                                                {{ $review->initials }}
                                            @endif
                                        </span>
                                        <span class="review-author-copy">
                                            <strong>{{ $review->customer_name }}</strong>
                                            <span>{{ $review->customer_title }}</span>
                                        </span>
                                    </footer>
                                </blockquote>
                            @endforeach
                        </div>
                        <div class="review-pagination" data-review-controls hidden>
                            <button type="button" class="review-pagination-arrow" data-review-previous aria-label="Previous reviews">←</button>
                            <div class="review-pagination-dots" data-review-dots aria-label="Choose a review page"></div>
                            <span class="review-pagination-status" data-review-status aria-live="polite"></span>
                            <button type="button" class="review-pagination-arrow" data-review-next aria-label="Next reviews">→</button>
                        </div>
                    </div>
                </div>
            @endif
        </section>

        <section class="section pricing-section" id="pricing">
            <div class="page-shell">
                <div class="section-heading section-heading-light reveal">
                    <p class="eyebrow centered eyebrow-light"><span></span> No bundled surprises <span></span></p>
                    <h2>Clear and itemised pricing</h2>
                    <p>Professional fees and UKIPO official fees shown separately.</p>
                </div>
                <div class="pricing-grid">
                    <article class="price-card price-card-service reveal">
                        <span class="popular-pill">Available now</span>
                        <p class="price-kicker">Opposition service</p>
                        <div class="coming-soon-service-icon" aria-hidden="true">⚖</div>
                        <h3>Defend or oppose a trade mark</h3>
                        <p class="coming-soon-copy">Choose the service that matches your opposition situation.</p>
                        <ul>
                            @foreach (['Flow A: defend your mark', 'Flow B: oppose a conflicting mark', 'Evidence and document review', 'Online case tracking'] as $feature)
                                <li><span>✓</span>{{ $feature }}</li>
                            @endforeach
                        </ul>
                        <a class="button button-dark" href="{{ route('trademark.opposition-management') }}">Start with opposition service <span>→</span></a>
                    </article>

                    <article class="price-card price-card-featured reveal">
                        <span class="popular-pill">Available now</span>
                        <p class="price-kicker">UK Trade Mark Application</p>
                        <div class="price">
                            <small>From</small>
                            @if ($applicationPriceDetails['coupon'])
                                <span class="price-before">£{{ number_format($applicationPriceDetails['original'], 0) }}</span>
                                <strong>£{{ number_format($applicationPriceDetails['current'], 0) }}</strong>
                                <span class="price-offer">{{ $applicationPriceDetails['coupon']->code }} · {{ $applicationPriceDetails['coupon']->discount_label }}</span>
                            @else
                                <strong>£{{ number_format($applicationPriceDetails['current'], 0) }}</strong>
                                <span>professional fee</span>
                            @endif
                        </div>
                        <ul>
                            @foreach (['Owner and mark review', 'Classes and specification', 'Client approval', 'UKIPO filing and tracking'] as $feature)
                                <li><span>✓</span>{{ $feature }}</li>
                            @endforeach
                        </ul>
                        <a class="button" href="{{ $applicationHref }}">Start your application <span>→</span></a>
                    </article>

                    <article class="price-card price-card-service reveal">
                        <span class="popular-pill">Available now</span>
                        <p class="price-kicker">Examination Report</p>
                        <div class="coming-soon-service-icon" aria-hidden="true">⌁</div>
                        <h3>Respond to an examination report</h3>
                        <p class="coming-soon-copy">Review, draft approval and filing support for examination objections.</p>
                        <ul>
                            @foreach (['Report and objection review', 'Reply strategy and drafting', 'Client draft approval', 'Registry filing and tracking'] as $feature)
                                <li><span>✓</span>{{ $feature }}</li>
                            @endforeach
                        </ul>
                        <a class="button button-dark" href="{{ route('examination-reply.landing') }}">Start with examination report <span>→</span></a>
                    </article>
                </div>
                <div class="official-fee-note reveal">
                    <span>i</span>
                    <div><strong>UKIPO fee reference</strong><p>£205 for the first class and £60 for each additional class. Complex objections, evidence, hearings and contentious matters are separately scoped.</p></div>
                </div>
            </div>
        </section>

        @if ($displayFaqs->isNotEmpty())
            <section class="section faq-section" id="faqs">
                <div class="page-shell faq-layout">
                    <div class="faq-intro reveal">
                        <p class="eyebrow"><span></span> Helpful answers</p>
                        <h2>UK trade mark FAQs</h2>
                        <p>Clear answers about filing, fees, review and what happens next.</p>
                        <a class="text-link" href="{{ route('contact') }}">Still have a question? Talk to us <span>→</span></a>
                    </div>
                    <div class="faq-list" data-faq-list>
                        @foreach ($displayFaqs as $index => $faq)
                            @php
                                $question = is_array($faq) ? $faq['question'] : $faq->question;
                                $answer = is_array($faq) ? $faq['answer'] : $faq->answer;
                            @endphp
                            <details class="faq-item reveal" {{ $index === 0 ? 'open' : '' }}>
                                <summary><span>{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span><strong>{{ $question }}</strong><i aria-hidden="true"></i></summary>
                                <div class="faq-answer"><p>{!! nl2br(e($answer)) !!}</p></div>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section class="final-cta">
            <div class="page-shell final-cta-inner reveal">
                <div><p class="eyebrow eyebrow-light"><span></span> Begin with clarity</p><h2>Ready to protect your brand in the UK?</h2><p>Start online or book a call to discuss the right next step.</p></div>
                <div><a class="button button-white" href="{{ $applicationHref }}">Start your application <span>→</span></a><a class="button button-ghost" href="{{ route('book-call.create') }}">Book a call</a></div>
            </div>
        </section>
    </main>

    @include('partials.site-footer')

    @include('partials.disclaimer-consent')

    <script>
        (() => {
            const toggle = document.querySelector('[data-nav-toggle]');
            const nav = document.querySelector('[data-nav]');
            toggle?.addEventListener('click', () => {
                const open = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', String(!open));
                nav?.classList.toggle('is-open', !open);
            });
            nav?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
                toggle?.setAttribute('aria-expanded', 'false');
                nav.classList.remove('is-open');
            }));

            document.querySelectorAll('[data-faq-list] details').forEach(item => {
                item.addEventListener('toggle', () => {
                    if (!item.open) return;
                    document.querySelectorAll('[data-faq-list] details[open]').forEach(other => {
                        if (other !== item) other.removeAttribute('open');
                    });
                });
            });

            const couponBanner = document.querySelector('[data-coupon-banner]');
            const couponCountdown = couponBanner?.querySelector('[data-coupon-countdown]');
            const couponEndsAt = couponBanner?.dataset.couponEndsAt;

            if (couponBanner && couponCountdown && couponEndsAt) {
                const targetTime = new Date(couponEndsAt).getTime();

                const updateCouponCountdown = () => {
                    const remaining = targetTime - Date.now();

                    if (!Number.isFinite(targetTime) || remaining <= 0) {
                        couponBanner.remove();
                        return false;
                    }

                    const totalSeconds = Math.floor(remaining / 1000);
                    const days = Math.floor(totalSeconds / 86400);
                    const hours = Math.floor((totalSeconds % 86400) / 3600);
                    const minutes = Math.floor((totalSeconds % 3600) / 60);
                    const seconds = totalSeconds % 60;

                    couponCountdown.textContent = `${days}d ${String(hours).padStart(2, '0')}h ${String(minutes).padStart(2, '0')}m ${String(seconds).padStart(2, '0')}s`;

                    return true;
                };

                if (updateCouponCountdown()) {
                    window.setInterval(updateCouponCountdown, 1000);
                }
            }

            document.querySelectorAll('[data-review-carousel]').forEach(carousel => {
                const track = carousel.querySelector('[data-review-track]');
                const cards = Array.from(carousel.querySelectorAll('[data-review-card]'));
                const controls = carousel.querySelector('[data-review-controls]');
                const dots = carousel.querySelector('[data-review-dots]');
                const status = carousel.querySelector('[data-review-status]');
                const previous = carousel.querySelector('[data-review-previous]');
                const next = carousel.querySelector('[data-review-next]');
                const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
                let currentPage = 0;
                let totalPages = 1;
                let reviewsPerPage = 1;
                let autoplayTimer = null;
                let scrollFrame = null;
                let resizeTimer = null;
                let paginationSyncTimer = null;
                let programmaticScroll = false;

                if (!track || !controls || !dots || !status || !previous || !next || cards.length === 0) return;

                const readReviewsPerPage = () => {
                    const value = Number.parseInt(getComputedStyle(track).getPropertyValue('--reviews-per-page'), 10);
                    return Number.isFinite(value) && value > 0 ? value : 1;
                };

                const pageStart = page => Math.min(page * reviewsPerPage, cards.length - 1);
                const pageOffset = page => {
                    const card = cards[pageStart(page)];
                    const requestedOffset = card.offsetLeft - cards[0].offsetLeft;
                    const maximumOffset = Math.max(track.scrollWidth - track.clientWidth, 0);

                    return Math.min(Math.max(requestedOffset, 0), maximumOffset);
                };

                const renderPagination = () => {
                    currentPage = Math.min(currentPage, totalPages - 1);
                    dots.querySelectorAll('button').forEach((dot, index) => {
                        const active = index === currentPage;
                        dot.classList.toggle('is-active', active);
                        dot.setAttribute('aria-current', active ? 'true' : 'false');
                    });
                    status.textContent = `${currentPage + 1} / ${totalPages}`;
                };

                const goToPage = (page, behavior = 'smooth') => {
                    currentPage = (page + totalPages) % totalPages;
                    programmaticScroll = true;
                    window.clearTimeout(paginationSyncTimer);
                    track.scrollTo({ left: pageOffset(currentPage), behavior });
                    renderPagination();
                    paginationSyncTimer = window.setTimeout(() => {
                        programmaticScroll = false;
                    }, behavior === 'smooth' ? 700 : 0);
                };

                const stopAutoplay = () => {
                    window.clearInterval(autoplayTimer);
                    autoplayTimer = null;
                };

                const startAutoplay = () => {
                    stopAutoplay();
                    if (totalPages <= 1 || reduceMotion.matches || document.hidden) return;
                    autoplayTimer = window.setInterval(() => goToPage(currentPage + 1), 4500);
                };

                const buildPagination = () => {
                    reviewsPerPage = readReviewsPerPage();
                    totalPages = Math.max(1, Math.ceil(cards.length / reviewsPerPage));
                    controls.hidden = totalPages <= 1;
                    dots.replaceChildren();

                    for (let page = 0; page < totalPages; page++) {
                        const dot = document.createElement('button');
                        dot.type = 'button';
                        dot.className = 'review-pagination-dot';
                        dot.setAttribute('aria-label', `Show review page ${page + 1}`);
                        dot.addEventListener('click', () => {
                            goToPage(page);
                            startAutoplay();
                        });
                        dots.appendChild(dot);
                    }

                    goToPage(Math.min(currentPage, totalPages - 1), 'auto');
                    startAutoplay();
                };

                previous.addEventListener('click', () => {
                    goToPage(currentPage - 1);
                    startAutoplay();
                });
                next.addEventListener('click', () => {
                    goToPage(currentPage + 1);
                    startAutoplay();
                });
                carousel.addEventListener('mouseenter', stopAutoplay);
                carousel.addEventListener('mouseleave', startAutoplay);
                carousel.addEventListener('focusin', stopAutoplay);
                carousel.addEventListener('focusout', event => {
                    if (!carousel.contains(event.relatedTarget)) startAutoplay();
                });
                track.addEventListener('pointerdown', () => {
                    programmaticScroll = false;
                    window.clearTimeout(paginationSyncTimer);
                });
                track.addEventListener('touchstart', () => {
                    programmaticScroll = false;
                    window.clearTimeout(paginationSyncTimer);
                    stopAutoplay();
                }, { passive: true });
                track.addEventListener('touchend', startAutoplay, { passive: true });
                track.addEventListener('scroll', () => {
                    if (programmaticScroll) return;
                    if (scrollFrame) return;
                    scrollFrame = window.requestAnimationFrame(() => {
                        const pageOffsets = Array.from({ length: totalPages }, (_, page) => pageOffset(page));
                        currentPage = pageOffsets.reduce((nearestPage, offset, page) => (
                            Math.abs(offset - track.scrollLeft) < Math.abs(pageOffsets[nearestPage] - track.scrollLeft) ? page : nearestPage
                        ), 0);
                        renderPagination();
                        scrollFrame = null;
                    });
                }, { passive: true });
                window.addEventListener('resize', () => {
                    window.clearTimeout(resizeTimer);
                    resizeTimer = window.setTimeout(buildPagination, 160);
                });
                document.addEventListener('visibilitychange', () => document.hidden ? stopAutoplay() : startAutoplay());
                reduceMotion.addEventListener?.('change', startAutoplay);

                buildPagination();
            });

            if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                const observer = new IntersectionObserver(entries => entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                }), { threshold: .12 });
                document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
            } else {
                document.querySelectorAll('.reveal').forEach(el => el.classList.add('is-visible'));
            }
        })();
    </script>
</body>
</html>
