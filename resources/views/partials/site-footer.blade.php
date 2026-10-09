@php
    $footerApplicationHref = auth()->check() ? route('trademark.type-selection') : route('register');
    $footerHome = route('landing');
    $regulatoryContent = \App\Models\CmsPage::findByKey(\App\Models\CmsPage::REGULATORY);
@endphp

<footer class="site-footer">
    <div class="site-footer-shell">
        <div class="footer-grid">
            <div class="footer-brand">
                <a class="footer-logo" href="{{ $footerHome }}" aria-label="Legal Bruz home">
                    <img src="{{ asset('legal-bruz-ltd-logo.png') }}" alt="Legal Bruz Ltd." width="180" height="134">
                </a>
                <p>UK trade mark searches, applications, routine prosecution, monitoring and renewals.</p>
                <div class="footer-socials" aria-label="Social media links">
                    @foreach (config('social_links') as $key => $social)
                        <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="Legal Bruz on {{ $social['label'] }}">
                            @if ($key === 'tiktok')
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.5 2c.4 2.3 1.8 3.7 4.1 3.9v3.2c-1.4.1-2.7-.3-4-1.1v7.1a6.5 6.5 0 1 1-5.6-6.4v3.3a3.3 3.3 0 1 0 2.3 3.1V2h3.2Z"/></svg>
                            @else
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5Zm0 2a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3H7Zm10.5 1.5a1 1 0 1 1 0 2 1 1 0 0 1 0-2ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/></svg>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>

            <nav class="footer-links" aria-label="Footer services">
                <h3>Services</h3>
                <a href="{{ route('trademark-search-report.create') }}">Trademark Search Report</a>
                <a href="{{ $footerApplicationHref }}">UK Application</a>
                <a href="{{ $footerHome }}#services">Classification</a>
                <a href="{{ route('examination-reply.landing') }}">Examination Report</a>
                <a href="{{ route('trademark.opposition-management') }}">Opposition Service</a>
            </nav>

            <nav class="footer-links" aria-label="Footer company links">
                <h3>Company</h3>
                <a href="{{ route('about') }}">About us</a>
                <a href="{{ route('blog.index') }}">Blogs</a>
                <a href="{{ $footerHome }}#process">How it works</a>
                <a href="{{ $footerHome }}#pricing">Pricing</a>
                <a href="{{ route('careers.index') }}">Careers</a>
                <a href="{{ route('contact') }}">Contact</a>
                <a href="{{ route('login') }}">Client login</a>
            </nav>

            <nav class="footer-links footer-legal" aria-label="Footer legal links">
                <h3>Legal</h3>
                <a href="{{ route('terms') }}">Terms &amp; Conditions</a>
                <a href="{{ route('privacy') }}">Privacy &amp; Cookies</a>
                <a href="{{ route('refund') }}">Cancellation &amp; Refunds</a>
                <a href="{{ route('faq') }}">FAQs</a>
            </nav>
        </div>

        <div class="regulatory">
            <strong>{{ $regulatoryContent['title'] }}</strong>
            <div>{!! $regulatoryContent['content'] !!}</div>
        </div>

        <div class="footer-bottom">
            <span>© {{ now()->year }} Legal Bruz Ltd. All rights reserved.</span>
            <span>UK trade mark services and online filing support.</span>
        </div>
    </div>
</footer>
