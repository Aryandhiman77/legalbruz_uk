@php
    $footerApplicationHref = auth()->check() ? route('trademark.type-selection') : route('register');
    $footerHome = route('landing');
@endphp

<footer class="site-footer">
    <div class="site-footer-shell">
        <div class="footer-grid">
            <div class="footer-brand">
                <a class="footer-logo" href="{{ $footerHome }}" aria-label="Legal Bruz home">
                    <img src="{{ asset('legal-bruz-pvt-ltd-logo.png') }}" alt="Legal Bruz Pvt. Ltd." width="180" height="134">
                </a>
                <p>UK trade mark searches, applications, routine prosecution, monitoring and renewals.</p>
                <div class="footer-socials" aria-label="Social media links">
                    @foreach (config('social_links') as $key => $social)
                        <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="Legal Bruz on {{ $social['label'] }}">
                            @if ($key === 'youtube')
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8ZM9.6 15.6V8.4L15.8 12l-6.2 3.6Z"/></svg>
                            @elseif ($key === 'facebook')
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M24 12a12 12 0 1 0-13.9 11.9v-8.4H7.1V12h3V9.3c0-3 1.8-4.7 4.5-4.7 1.3 0 2.7.2 2.7.2v3h-1.5c-1.5 0-2 .9-2 1.9V12h3.4l-.5 3.5h-2.9v8.4A12 12 0 0 0 24 12Z"/></svg>
                            @else
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5Zm0 2a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3H7Zm10.5 1.5a1 1 0 1 1 0 2 1 1 0 0 1 0-2ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/></svg>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>

            <nav class="footer-links" aria-label="Footer services">
                <h3>Services</h3>
                <a href="{{ $footerHome }}#trademark-search">Trade Mark Search</a>
                <a href="{{ $footerApplicationHref }}">UK Application</a>
                <a href="{{ $footerHome }}#services">Classification</a>
                <a href="{{ route('contact') }}">Examination Response</a>
            </nav>

            <nav class="footer-links" aria-label="Footer company links">
                <h3>Company</h3>
                <a href="{{ route('about') }}">About us</a>
                <a href="{{ $footerHome }}#process">How it works</a>
                <a href="{{ $footerHome }}#pricing">Pricing</a>
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
            <strong>Regulatory information</strong>
            <p>Legal Bruz Pvt. Ltd. provides permitted unreserved legal and intellectual property services. Legal Bruz Pvt. Ltd. is not authorised or regulated by the Solicitors Regulation Authority or the Intellectual Property Regulation Board and is not an SRA-authorised law firm or an IPReg-regulated trade mark attorney firm.</p>
            <p class="verification-note">Company number, registered address, UK email address and any reference to Registered Foreign Lawyer status must be verified before publication.</p>
        </div>

        <div class="footer-bottom">
            <span>© {{ now()->year }} Legal Bruz Pvt. Ltd. All rights reserved.</span>
            <span>UK trade mark services and online filing support.</span>
        </div>
    </div>
</footer>
