<aside class="auth-panel">
    <a class="auth-brand" href="{{ route('landing') }}" aria-label="Legal Bruz home">
        <span class="auth-brand-mark">
            <img src="{{ asset('legal-bruz-pvt-ltd-logo.png') }}" alt="Legal Bruz Pvt. Ltd. emblem" width="82" height="82">
        </span>
    </a>

    <div class="auth-panel-copy">
        @if ($mode === 'register')
            <h1>Create Your<br><span>Account</span></h1>
            <p>Join Legal Bruz and simplify your legal workflow with practical tools and expert support.</p>

            <div class="auth-benefits">
                <div class="auth-benefit">
                    <span class="auth-benefit-icon"><i class="bi bi-shield-check"></i></span>
                    <span><strong>Secure &amp; Reliable</strong><small>Your details are encrypted and protected.</small></span>
                </div>
                <div class="auth-benefit">
                    <span class="auth-benefit-icon"><i class="bi bi-clock-history"></i></span>
                    <span><strong>Quick &amp; Easy</strong><small>Get started in less than two minutes.</small></span>
                </div>
                <div class="auth-benefit">
                    <span class="auth-benefit-icon"><i class="bi bi-headset"></i></span>
                    <span><strong>Dedicated Support</strong><small>We're here whenever you need help.</small></span>
                </div>
            </div>
        @elseif ($mode === 'otp')
            <h1>One More<br><span>Secure Step</span></h1>
            <p>A short email check helps us protect your Legal Bruz account from unauthorized access.</p>
        @else
            <h1>Welcome <span>Back!</span></h1>
            <p>Sign in to access your Legal Bruz dashboard and manage your work seamlessly.</p>
        @endif
    </div>

    <div class="auth-panel-footer auth-secure">
        <i class="bi bi-shield-check"></i>
        <span>Your data is secure<br>and protected</span>
    </div>
</aside>
