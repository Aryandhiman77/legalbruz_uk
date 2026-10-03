@extends('layouts.app')

@section('title', 'Create Account | Legal Bruz')
@section('body_class', 'auth-body')
@section('head')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endsection

@section('content')
<section class="auth-page">
    <div class="auth-shell auth-shell--register">
        @include('auth.partials.panel', ['mode' => 'register'])

        <div class="auth-form-panel">
            <div class="auth-form-wrap">
                <header class="auth-heading">
                    <span class="auth-heading-icon"><i class="bi bi-person-plus"></i></span>
                    <div>
                        <h2>Register</h2>
                        <p>Fill in the details below to create your account</p>
                    </div>
                </header>

                <form method="POST" action="{{ route('register') }}" data-auth-form>
                    @csrf

                    <div class="auth-field">
                        <label for="name">Full Name</label>
                        <div class="auth-input-wrap">
                            <i class="bi bi-person auth-input-icon" aria-hidden="true"></i>
                            <input id="name" type="text" class="auth-input @error('name') is-invalid @enderror"
                                name="name" value="{{ old('name') }}" required minlength="2" maxlength="100"
                                autocomplete="name" autofocus placeholder="Enter your full name">
                        </div>
                        @error('name')<span class="auth-error" role="alert">{{ $message }}</span>@enderror
                    </div>

                    <div class="auth-field">
                        <label for="email">Email Address</label>
                        <div class="auth-input-wrap">
                            <i class="bi bi-envelope auth-input-icon" aria-hidden="true"></i>
                            <input id="email" type="email" class="auth-input @error('email') is-invalid @enderror"
                                name="email" value="{{ old('email') }}" required maxlength="255"
                                autocomplete="email" inputmode="email" placeholder="Enter your email address">
                        </div>
                        @error('email')<span class="auth-error" role="alert">{{ $message }}</span>@enderror
                    </div>

                    <div class="auth-field">
                        <label for="mobile">UK Mobile Number</label>
                        <div class="auth-input-wrap">
                            <i class="bi bi-telephone auth-input-icon" aria-hidden="true"></i>
                            <input id="mobile" type="tel" class="auth-input @error('mobile') is-invalid @enderror"
                                name="mobile" value="{{ old('mobile') }}" required inputmode="tel" maxlength="16"
                                autocomplete="tel" pattern="(?:\+44 ?7|07)(?:[0-9] ?){9}"
                                placeholder="07123 456789" title="Enter a valid UK mobile number">
                        </div>
                        <small class="auth-help">Use a UK mobile number beginning with 07 or +44 7.</small>
                        @error('mobile')<span class="auth-error" role="alert">{{ $message }}</span>@enderror
                    </div>

                    <div class="auth-field">
                        <label for="password">Password</label>
                        <div class="auth-input-wrap">
                            <i class="bi bi-lock auth-input-icon" aria-hidden="true"></i>
                            <input id="password" type="password" class="auth-input @error('password') is-invalid @enderror"
                                name="password" required minlength="8" autocomplete="new-password"
                                pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,}" placeholder="Create a password"
                                title="Use at least 8 characters, including a letter and a number">
                            <button type="button" class="auth-toggle-password" data-password-toggle="password" aria-label="Show password">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <small class="auth-help">At least 8 characters with a letter and a number.</small>
                        @error('password')<span class="auth-error" role="alert">{{ $message }}</span>@enderror
                    </div>

                    <div class="auth-field">
                        <label for="password-confirm">Confirm Password</label>
                        <div class="auth-input-wrap">
                            <i class="bi bi-lock-fill auth-input-icon" aria-hidden="true"></i>
                            <input id="password-confirm" type="password" class="auth-input"
                                name="password_confirmation" required minlength="8" autocomplete="new-password"
                                placeholder="Confirm your password">
                            <button type="button" class="auth-toggle-password" data-password-toggle="password-confirm" aria-label="Show password">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="auth-submit" data-loading-text="Creating account…">
                        <i class="bi bi-person-check" aria-hidden="true"></i>
                        <span data-submit-label>Register</span>
                    </button>

                    <div class="auth-separator">or</div>
                    <p class="auth-switch">Already have an account? <a class="auth-link" href="{{ route('login') }}">Login</a></p>
                </form>
            </div>
        </div>
    </div>
</section>

@include('auth.partials.scripts')
<script>
    const password = document.getElementById('password');
    const confirmation = document.getElementById('password-confirm');
    const validateConfirmation = () => confirmation.setCustomValidity(
        confirmation.value && confirmation.value !== password.value ? 'Passwords do not match.' : ''
    );
    password.addEventListener('input', validateConfirmation);
    confirmation.addEventListener('input', validateConfirmation);
</script>
@endsection
