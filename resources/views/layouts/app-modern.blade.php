<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Legal Bruz Pvt. Ltd.') . ' - IPR Registration')</title>
    <link rel="icon" type="image/png" href="{{ asset('legal-bruz-pvt-ltd-logo.png') }}">
    <meta name="description" content="@yield('meta_description', 'Legal Bruz provides clear UK trade mark application preparation, filing support and online tracking.')">
    <meta name="robots" content="@yield('meta_robots', request()->is('admin*', 'login', 'register', 'dashboard*', 'home') ? 'noindex, nofollow' : 'index, follow, max-image-preview:large')">
    <link rel="canonical" href="@yield('canonical_url', url()->current())">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="Legal Bruz">
    <meta property="og:title" content="@yield('og_title', 'Legal Bruz - Intellectual Property Services')">
    <meta property="og:description" content="@yield('og_description', 'Trademark and intellectual property services made clear and accessible.')">
    <meta property="og:url" content="@yield('canonical_url', url()->current())">
    <meta property="og:image" content="@yield('og_image', asset('legal-bruz-pvt-ltd-logo.png'))">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', 'Legal Bruz - Intellectual Property Services')">
    <meta name="twitter:description" content="@yield('og_description', 'Trademark and intellectual property services made clear and accessible.')">
    <meta name="twitter:image" content="@yield('og_image', asset('legal-bruz-pvt-ltd-logo.png'))">
    @yield('head')

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap"
        rel="stylesheet">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/design-system.css') }}">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --navy: #1D3557;
            --emerald: #2A9D8F;
            --slate: #4A4A4A;
            --light-bg: #F4F4F9;
            --white: #FFFFFF;
            --border: #E8E8EE;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--light-bg);
            color: var(--slate);
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: 'Manrope', sans-serif;
            color: var(--navy);
            font-weight: 800;
        }

        /* ============ NAVBAR ============ */
        nav.navbar {
            background: var(--white);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-size: 1.6rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--navy) 0%, var(--emerald) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }

        .nav-link {
            color: var(--slate) !important;
            font-weight: 600;
            font-size: 0.9rem;
            margin: 0 10px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: var(--emerald) !important;
        }

        .btn-nav-logout {
            background: linear-gradient(135deg, var(--emerald) 0%, #228974 100%);
            color: var(--white) !important;
            font-weight: 700;
            padding: 8px 20px !important;
            border-radius: 6px !important;
            font-size: 0.9rem;
            border: none !important;
        }

        .btn-nav-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(42, 157, 143, 0.3);
        }

        /* Responsive Logo Styling */
        .navbar-logo {
            display: block;
            width: 106px;
            height: 79px;
            max-width: 106px;
            max-height: 79px;
            object-fit: contain;
        }

        @media (max-width: 768px) {
            .navbar-logo {
                width: 92px;
                height: 68px;
                max-width: 92px;
                max-height: 68px;
            }
        }

        @media (max-width: 480px) {
            .navbar-logo {
                width: 84px;
                height: 62px;
                max-width: 84px;
                max-height: 62px;
            }
        }

        /* ============ MAIN CONTENT ============ */
        #app {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        main {
            flex: 1;
            padding: 40px 0;
        }

        /* ============ PROGRESS BAR ============ */
        .progress-section {
            background: var(--white);
            padding: 30px 0;
            border-bottom: 1px solid var(--border);
            margin-bottom: 40px;
        }

        .step-tracker {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }

        .step-tracker::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--border);
            z-index: 0;
        }

        .step-item {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .step-number {
            width: 45px;
            height: 45px;
            background: var(--white);
            border: 3px solid var(--border);
            color: var(--slate);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            margin: 0 auto 10px;
            transition: all 0.3s ease;
        }

        .step-item.active .step-number {
            background: linear-gradient(135deg, var(--emerald) 0%, #228974 100%);
            color: var(--white);
            border-color: var(--emerald);
            box-shadow: 0 8px 20px rgba(42, 157, 143, 0.3);
        }

        .step-item.completed .step-number {
            background: var(--emerald);
            color: var(--white);
            border-color: var(--emerald);
        }

        .step-item.completed::before {
            content: '✓';
            position: absolute;
            top: 5px;
            right: 0;
            font-size: 1.2rem;
            font-weight: 900;
            color: var(--emerald);
        }

        .step-label {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--navy);
        }

        .step-item.active .step-label {
            color: var(--emerald);
        }

        /* ============ CARDS ============ */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .card-header {
            background: linear-gradient(135deg, var(--navy) 0%, #2d4a73 100%) !important;
            color: var(--white) !important;
            border: none !important;
            padding: 30px !important;
            border-radius: 12px 12px 0 0 !important;
        }

        .card-header h2 {
            color: var(--white) !important;
            margin-bottom: 5px;
            font-size: 1.5rem;
        }

        .card-header small {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.9rem;
        }

        .card-body {
            padding: 35px !important;
        }

        /* ============ FORMS ============ */
        .form-label {
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .form-control,
        .form-select {
            border: 2px solid var(--border);
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--emerald);
            box-shadow: 0 0 0 0.2rem rgba(42, 157, 143, 0.1);
        }

        .form-control.is-invalid,
        .form-select.is-invalid {
            border-color: #dc3545;
        }

        .invalid-feedback {
            font-size: 0.85rem;
            color: #dc3545;
            font-weight: 600;
        }

        .form-text {
            font-size: 0.85rem;
            color: var(--slate);
        }

        .required::after {
            content: '*';
            color: #dc3545;
            margin-left: 3px;
            font-weight: 700;
        }

        /* ============ BUTTONS ============ */
        .btn {
            font-weight: 700;
            padding: 12px 28px;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: none;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--emerald) 0%, #228974 100%);
            color: var(--white) !important;
            box-shadow: 0 8px 20px rgba(42, 157, 143, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(42, 157, 143, 0.35);
        }

        .btn-secondary {
            background: var(--slate);
            color: var(--white) !important;
        }

        .btn-secondary:hover {
            background: #3a3a3a;
            transform: translateY(-3px);
        }

        .btn-outline-secondary {
            border: 2px solid var(--border);
            color: var(--slate) !important;
        }

        .btn-outline-secondary:hover {
            background: var(--light-bg);
            border-color: var(--slate);
        }

        .btn-lg {
            padding: 15px 40px;
            font-size: 1rem;
        }

        /* ============ ALERTS ============ */
        .alert {
            border: none;
            border-radius: 8px;
            padding: 16px 20px;
            font-size: 0.95rem;
        }

        .alert-info {
            background: linear-gradient(135deg, rgba(42, 157, 143, 0.1) 0%, rgba(42, 157, 143, 0.05) 100%);
            color: var(--navy);
            border-left: 4px solid var(--emerald);
        }

        .alert-warning {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1) 0%, rgba(255, 193, 7, 0.05) 100%);
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(42, 157, 143, 0.15) 0%, rgba(42, 157, 143, 0.08) 100%);
            color: #2A9D8F;
            border-left: 4px solid var(--emerald);
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1) 0%, rgba(220, 53, 69, 0.05) 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert i {
            margin-right: 10px;
            font-size: 1.1rem;
        }

        /* ============ OPTION CARDS ============ */
        .option-card {
            background: var(--white);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 35px;
            text-align: center;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.23, 1, 0.320, 1);
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .option-card:hover {
            border-color: var(--emerald);
            box-shadow: 0 20px 50px rgba(42, 157, 143, 0.15);
            transform: translateY(-10px);
        }

        .option-card i {
            font-size: 3.5rem;
            margin-bottom: 20px;
            color: var(--emerald);
        }

        .option-card h3 {
            font-size: 1.4rem;
            margin-bottom: 12px;
        }

        .option-card p {
            color: var(--slate);
            font-size: 0.95rem;
            margin-bottom: 20px;
        }

        .option-card .btn {
            width: 100%;
        }

        /* ============ CHECKLIST ============ */
        .checklist-item {
            background: var(--white);
            border: 2px solid var(--border);
            border-radius: 8px;
            padding: 18px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .checklist-item:hover {
            border-color: var(--emerald);
            background: linear-gradient(135deg, var(--white) 0%, rgba(42, 157, 143, 0.02) 100%);
        }

        .form-check-input {
            width: 24px;
            height: 24px;
            border: 2px solid var(--emerald);
            border-radius: 6px;
            margin-top: 2px;
            cursor: not-allowed;
        }

        .form-check-input:checked {
            background-color: var(--emerald);
            border-color: var(--emerald);
        }

        .form-check-label {
            font-weight: 600;
            color: var(--navy);
            cursor: default;
            margin-left: 10px;
        }

        .checklist-item small {
            display: block;
            color: var(--slate);
            font-size: 0.85rem;
            margin-top: 5px;
        }

        /* ============ FILE UPLOAD ============ */
        .file-upload-area {
            border: 2px dashed var(--emerald);
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background: linear-gradient(135deg, rgba(42, 157, 143, 0.05) 0%, rgba(42, 157, 143, 0.02) 100%);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-area:hover {
            background: linear-gradient(135deg, rgba(42, 157, 143, 0.1) 0%, rgba(42, 157, 143, 0.05) 100%);
            border-color: #228974;
        }

        .file-upload-area i {
            font-size: 3rem;
            color: var(--emerald);
            margin-bottom: 15px;
        }

        .file-upload-area p {
            margin: 10px 0;
            color: var(--slate);
        }

        .file-upload-area .upload-text {
            font-weight: 700;
            color: var(--navy);
        }

        /* ============ FOOTER ============ */
        footer {
            background: var(--navy);
            color: var(--white);
            padding: 30px 0;
            margin-top: 60px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        footer p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.85;
        }

        /* ============ RESPONSIVE ============ */
        @media (max-width: 768px) {
            .card-body {
                padding: 20px !important;
            }

            .card-header {
                padding: 20px !important;
            }

            .card-header h2 {
                font-size: 1.2rem;
            }

            .step-tracker {
                gap: 10px;
            }

            .step-label {
                font-size: 0.7rem;
            }

            .option-card {
                padding: 20px;
            }

            .option-card i {
                font-size: 2.5rem;
            }

            .file-upload-area {
                padding: 25px;
            }

            .file-upload-area i {
                font-size: 2rem;
            }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/mobile-typography.css') }}">
    <link rel="stylesheet" href="{{ asset('css/uk-site-theme.css') }}">
    <link rel="stylesheet" href="{{ asset('css/site-footer.css') }}?v={{ filemtime(public_path('css/site-footer.css')) }}">
</head>

<body class="uk-site-body">
    <div id="app">
        <!-- ============ NAVBAR ============ -->
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center gap-2" href="{{ url('/') }}">
                    <img src="{{ asset('legal-bruz-pvt-ltd-logo.png') }}" alt="Legal Bruz Pvt. Ltd. logo" class="navbar-logo" width="106" height="79">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto align-items-center">
                        @auth
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#">Help</a>
                            </li>
                            <li class="nav-item ms-2">
                                <form method="POST" action="{{ route('logout') }}" style="display: inline;"
                                    data-swal-confirm data-swal-title="Log out?"
                                    data-swal-text="You will be signed out of your account." data-swal-icon="question"
                                    data-swal-confirm-text="Yes, log out">
                                    @csrf
                                    <button type="submit" class="btn btn-nav-logout">Logout</button>
                                </form>
                            </li>
                        @else
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('login') }}">Login</a>
                            </li>
                            <li class="nav-item ms-2">
                                <a href="{{ route('register') }}" class="btn btn-nav-logout">Sign Up</a>
                            </li>
                        @endauth
                    </ul>
                </div>
            </div>
        </nav>

        <!-- ============ MAIN CONTENT ============ -->
        <main>
            @yield('content')
        </main>

        <!-- ============ FOOTER ============ -->
        @include('partials.site-footer')
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add smooth transitions
        document.addEventListener('DOMContentLoaded', function() {
            // Animate elements on scroll
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animation = 'fadeInUp 0.6s ease-out forwards';
                    }
                });
            }, {
                threshold: 0.1
            });

            document.querySelectorAll('.option-card, .checklist-item').forEach(el => {
                el.style.opacity = '0';
                observer.observe(el);
            });
        });
    </script>
    <style>
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
    @include('partials.disclaimer-consent')
    @include('partials.button-loading')
    @include('partials.sweet-alert-confirmations')
</body>

</html>
