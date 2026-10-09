<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Legal Bruz Ltd.') . ' - IPR Registration')</title>
    <link rel="icon" type="image/png" href="{{ asset('legal-bruz-ltd-logo.png') }}">
    <meta name="description" content="@yield('meta_description', 'Legal Bruz provides UK trade mark searches, application preparation and filing support for businesses, founders and brand owners.')">
    <meta name="robots" content="@yield('meta_robots', request()->is('admin*', 'login', 'register', 'dashboard*', 'home') ? 'noindex, nofollow' : 'index, follow, max-image-preview:large')">
    <link rel="canonical" href="@yield('canonical_url', url()->current())">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="Legal Bruz">
    <meta property="og:title" content="@yield('og_title', 'Legal Bruz - Intellectual Property Services')">
    <meta property="og:description" content="@yield('og_description', 'Trademark and intellectual property services made clear and accessible.')">
    <meta property="og:url" content="@yield('canonical_url', url()->current())">
    <meta property="og:image" content="@yield('og_image', asset('legal-bruz-ltd-logo.png'))">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', 'Legal Bruz - Intellectual Property Services')">
    <meta name="twitter:description" content="@yield('og_description', 'Trademark and intellectual property services made clear and accessible.')">
    <meta name="twitter:image" content="@yield('og_image', asset('legal-bruz-ltd-logo.png'))">
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
            background: #fff;
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
    <link rel="stylesheet" href="{{ asset('css/mobile-typography.css') }}">
    <style>
        .admin-topbar {
            position: sticky;
            top: 0;
            z-index: 1040;
            height: 74px;
            border-bottom: 1px solid #dfe7ef;
            background: #ffffff;
            box-shadow: 0 4px 18px rgba(7, 31, 72, .07);
        }

        .admin-topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            height: 100%;
            padding: 0 22px;
        }

        .admin-topbar-brand {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            color: #071f48;
            text-decoration: none;
        }

        .admin-topbar-logo {
            position: relative;
            display: block;
            width: 44px;
            height: 44px;
            overflow: hidden;
            border: 1px solid rgba(13, 148, 136, .35);
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 4px 12px rgba(7, 31, 72, .14);
        }

        .admin-topbar-logo img {
            position: absolute;
            top: -17px;
            left: 50%;
            width: 122px;
            max-width: none;
            height: auto;
            transform: translateX(-50%);
        }

        .admin-topbar-brand strong,
        .admin-topbar-brand small {
            display: block;
        }

        .admin-topbar-brand strong {
            font-size: .96rem;
            line-height: 1.2;
        }

        .admin-topbar-brand small {
            margin-top: 2px;
            color: #718096;
            font-size: .67rem;
            font-weight: 700;
            letter-spacing: .04em;
        }

        .admin-sidebar-toggle {
            display: grid;
            place-items: center;
            width: 40px;
            height: 40px;
            border: 1px solid #dce5ee;
            border-radius: 9px;
            color: #071f48;
            background: #f7f9fc;
            font-size: 1.35rem;
        }

        .admin-profile-button {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px 9px 5px 5px;
            border: 1px solid #dfe7ef;
            border-radius: 11px;
            color: #142943;
            background: #fff;
            text-align: left;
        }

        .admin-profile-avatar {
            display: grid;
            place-items: center;
            width: 36px;
            height: 36px;
            border-radius: 9px;
            color: #fff;
            background: linear-gradient(135deg, #0d5167, #159f8d);
            font-size: .82rem;
            font-weight: 900;
        }

        .admin-profile-copy strong,
        .admin-profile-copy small {
            display: block;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .admin-profile-copy strong {
            font-size: .78rem;
        }

        .admin-profile-copy small {
            color: #7a8798;
            font-size: .63rem;
        }

        .admin-profile-menu {
            min-width: 230px;
            padding: 8px;
            border: 1px solid #dfe7ef;
            border-radius: 11px;
            box-shadow: 0 14px 35px rgba(7, 31, 72, .14);
        }

        .admin-layout-shell {
            display: block;
            min-height: calc(100vh - 74px);
            background: #f4f7fa;
        }

        .admin-sidebar {
            position: fixed;
            top: 74px;
            bottom: 0;
            left: 0;
            z-index: 1060;
            display: flex;
            width: min(286px, calc(100vw - 44px));
            flex-direction: column;
            height: auto;
            overflow-y: auto;
            color: #dce8f5;
            background:
                radial-gradient(circle at 100% 0, rgba(25, 159, 141, .18), transparent 30%),
                #071f48;
            box-shadow: 16px 0 40px rgba(4, 18, 44, .24);
            transform: translateX(-105%);
            transition: transform .22s ease;
        }

        .admin-sidebar-open .admin-sidebar {
            transform: translateX(0);
        }

        .admin-sidebar-nav {
            flex: 1;
            padding: 20px 14px;
        }

        .admin-nav-group + .admin-nav-group {
            margin-top: 22px;
        }

        .admin-nav-label {
            margin: 0 10px 7px;
            color: #7f99b7;
            font-size: .61rem;
            font-weight: 900;
            letter-spacing: .13em;
            text-transform: uppercase;
        }

        .admin-nav-link {
            position: relative;
            display: flex;
            align-items: center;
            gap: 11px;
            min-height: 42px;
            margin: 3px 0;
            padding: 0 12px;
            border-radius: 9px;
            color: #c7d5e5;
            font-size: .76rem;
            font-weight: 750;
            text-decoration: none;
            transition: background .18s ease, color .18s ease, transform .18s ease;
        }

        .admin-nav-link i {
            width: 20px;
            color: #7fabc0;
            font-size: .97rem;
            text-align: center;
        }

        .admin-nav-link:hover {
            color: #fff;
            background: rgba(255, 255, 255, .075);
            transform: translateX(2px);
        }

        .admin-nav-link.active {
            color: #fff;
            background: linear-gradient(100deg, rgba(21, 159, 141, .95), rgba(21, 159, 141, .65));
            box-shadow: 0 7px 18px rgba(0, 0, 0, .14);
        }

        .admin-nav-link.active i {
            color: #fff;
        }

        .admin-sidebar-footer {
            padding: 14px;
            border-top: 1px solid rgba(255, 255, 255, .09);
        }

        .admin-sidebar-footer a,
        .admin-sidebar-logout {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            min-height: 42px;
            padding: 0 12px;
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 9px;
            color: #d8e6f2;
            background: transparent;
            font-size: .73rem;
            font-weight: 750;
            text-decoration: none;
            transition: background .18s ease, border-color .18s ease, color .18s ease;
        }

        .admin-sidebar-footer form {
            margin-top: 8px;
        }

        .admin-sidebar-footer a:hover {
            color: #fff;
            border-color: rgba(255, 255, 255, .22);
            background: rgba(255, 255, 255, .07);
        }

        .admin-sidebar-logout {
            color: #ffc5c9;
            cursor: pointer;
        }

        .admin-sidebar-logout:hover {
            color: #fff;
            border-color: rgba(255, 133, 143, .3);
            background: rgba(220, 53, 69, .16);
        }

        .admin-status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            max-width: 220px;
            min-height: 27px;
            padding: 4px 10px;
            border: 1px solid var(--status-border);
            border-radius: 999px;
            color: var(--status-text);
            background: var(--status-bg);
            font-size: .7rem;
            font-weight: 850;
            line-height: 1.25;
            text-align: center;
        }

        .admin-status-pill i {
            flex: 0 0 6px;
            width: 6px;
            height: 6px;
            margin-right: 6px;
            border-radius: 50%;
            background: var(--status-dot);
        }

        .admin-status-native {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            margin: -1px !important;
            padding: 0 !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }

        .admin-status-select {
            position: relative;
            width: min(260px, 100%);
            min-width: 180px;
        }

        .admin-status-select.is-open {
            z-index: 1121;
        }

        .admin-status-menu-host {
            position: relative !important;
            z-index: 1120 !important;
            overflow: visible !important;
            transform: none !important;
        }

        .admin-status-select-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            min-height: 38px;
            padding: 7px 34px 7px 11px;
            border: 1px solid var(--status-border, #d5dee8);
            border-radius: 8px;
            color: var(--status-text, #334155);
            background: var(--status-bg, #fff);
            font-size: .73rem;
            font-weight: 750;
            text-align: left;
            cursor: pointer;
        }

        .admin-status-select-toggle::after {
            content: "";
            position: absolute;
            right: 13px;
            width: 7px;
            height: 7px;
            border-right: 2px solid currentColor;
            border-bottom: 2px solid currentColor;
            transform: translateY(-2px) rotate(45deg);
            opacity: .7;
        }

        .admin-status-select.is-open .admin-status-select-toggle::after {
            transform: translateY(2px) rotate(225deg);
        }

        .admin-status-select-dot,
        .admin-status-select-option i {
            flex: 0 0 8px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--status-dot, #94a3b8);
        }

        .admin-status-select-menu {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            z-index: 1122;
            display: none;
            width: max(100%, 310px);
            max-height: 340px;
            overflow-y: auto;
            padding: 6px;
            border: 1px solid #dbe3ec;
            border-radius: 11px;
            background: #fff;
            box-shadow: 0 18px 45px rgba(15, 35, 62, .18);
        }

        .admin-status-select.is-open .admin-status-select-menu {
            display: grid;
            gap: 4px;
        }

        .admin-status-select-option {
            display: grid;
            grid-template-columns: 10px minmax(0, 1fr);
            gap: 9px;
            align-items: center;
            width: 100%;
            padding: 9px 10px;
            border: 1px solid transparent;
            border-radius: 8px;
            color: var(--status-text, #334155);
            background: var(--status-bg, #fff);
            font-size: .72rem;
            text-align: left;
            cursor: pointer;
        }

        .admin-status-select-option:hover,
        .admin-status-select-option[aria-selected="true"] {
            border-color: var(--status-border, #cbd5e1);
            box-shadow: inset 0 0 0 1px var(--status-border, #cbd5e1);
        }

        .admin-status-select-option strong,
        .admin-status-select-option small {
            display: block;
        }

        .admin-status-select-option strong {
            font-size: .72rem;
        }

        .admin-status-select-option small {
            margin-top: 2px;
            color: inherit;
            font-size: .62rem;
            font-weight: 650;
            opacity: .72;
        }

        @media (max-width: 575px) {
            .admin-status-select { width: 100%; }
            .admin-status-select-menu { width: 100%; min-width: 280px; }
        }

        .admin-layout-main {
            width: 100%;
            min-width: 0;
            padding: 24px 18px 44px;
        }

        .admin-layout-main > .container,
        .admin-layout-main > .container-fluid {
            max-width: 1500px;
        }

        .admin-layout-main .table-responsive,
        .admin-layout-main .admin-table-scroll {
            display: block;
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            border: 1px solid #e1e8f0;
            border-radius: 12px;
            background: #fff;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: #b7c5d3 #edf2f7;
        }

        .admin-layout-main table {
            width: 100% !important;
            max-width: 100%;
            margin: 0 !important;
            color: #35445a;
            border-collapse: separate !important;
            border-spacing: 0 !important;
        }

        .admin-layout-main .admin-list-table,
        .admin-layout-main .table-responsive > table,
        .admin-layout-main .admin-table-scroll > table {
            min-width: 780px;
            table-layout: auto;
        }

        .admin-layout-main table thead th {
            height: 52px;
            padding: 13px 15px !important;
            border-top: 0 !important;
            border-bottom: 1px solid #dce5ee !important;
            color: #53647a !important;
            background: #f6f8fb !important;
            font-size: .72rem !important;
            font-weight: 850 !important;
            letter-spacing: .055em;
            line-height: 1.25 !important;
            text-transform: uppercase;
            white-space: nowrap;
            vertical-align: middle !important;
        }

        .admin-layout-main table tbody td {
            height: 62px;
            padding: 14px 15px !important;
            border-top: 0 !important;
            border-bottom: 1px solid #e8edf3 !important;
            color: #35445a !important;
            font-size: .82rem !important;
            line-height: 1.45 !important;
            vertical-align: middle !important;
        }

        .admin-layout-main table tbody tr:last-child td {
            border-bottom: 0 !important;
        }

        .admin-layout-main table tbody tr:hover td {
            background: #f9fbfd !important;
        }

        .admin-layout-main table td strong {
            color: #172b46;
            font-size: .84rem;
            font-weight: 800;
        }

        .admin-layout-main table td small {
            color: #758398;
            font-size: .72rem;
            line-height: 1.4;
        }

        .admin-layout-main table .badge {
            max-width: 190px;
            padding: 6px 9px;
            border-radius: 999px;
            font-size: .69rem;
            font-weight: 800;
            line-height: 1.25;
            white-space: normal;
        }

        .admin-layout-main table .btn,
        .admin-layout-main table .admin-btn {
            min-height: 34px;
            padding: 7px 11px;
            border-radius: 7px;
            font-size: .73rem;
            line-height: 1.1;
            white-space: nowrap;
        }

        .admin-layout-main table th:last-child,
        .admin-layout-main table td:last-child {
            white-space: nowrap;
        }

        .admin-table-empty {
            padding: 42px 20px !important;
            color: #78869a !important;
            text-align: center;
        }

        .admin-sidebar-overlay {
            position: fixed;
            inset: 74px 0 0;
            z-index: 1050;
            display: none;
            border: 0;
            background: rgba(5, 18, 42, .46);
            backdrop-filter: blur(2px);
        }

        .admin-sidebar-open .admin-sidebar-overlay {
            display: block;
        }

        body.admin-sidebar-open {
            overflow: hidden;
        }

        @media (max-width: 991.98px) {
            .admin-layout-main {
                padding: 20px 10px 38px;
            }
        }

        @media (max-width: 575.98px) {
            .admin-topbar-inner {
                padding: 0 12px;
            }

            .admin-profile-copy,
            .admin-topbar-brand small {
                display: none;
            }

            .admin-topbar-brand strong {
                font-size: .85rem;
            }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/uk-site-theme.css') }}">
    <link rel="stylesheet" href="{{ asset('css/site-footer.css') }}?v={{ filemtime(public_path('css/site-footer.css')) }}">
</head>

<body class="@yield('body_class') {{ request()->is('admin*') && Auth::guard('admin')->check() ? 'admin-body' : 'uk-site-body' }}">
    <div id="app">
        <!-- ============ NAVBAR ============ -->
        @if (request()->is('admin*') && Auth::guard('admin')->check())
            @include('components.admin-header')
        @elseif (Auth::check())
            <!-- Authenticated User Header -->
            @include('components.user-header')
        @else
            <!-- Guest Header -->
            <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
                <div class="container">
                    <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="{{ route('landing') }}"
                        style="font-size: 1.5rem; color: #1D3557;">
                        <img src="{{ asset('legal-bruz-ltd-logo.png') }}" alt="Legal Bruz Ltd. logo" class="navbar-logo" width="106" height="79">

                    </a>

                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                        data-bs-target="#guestNavbar" aria-controls="guestNavbar" aria-expanded="false"
                        aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <div class="collapse navbar-collapse" id="guestNavbar">
                        <ul class="navbar-nav ms-auto align-items-center gap-3">
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('landing') }}">Home</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('about') ? 'active' : '' }}" href="{{ route('about') }}">About Us</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('login') }}">Login</a>
                            </li>
                            <li class="nav-item">
                                <a class="btn btn-nav-logout" href="{{ route('register') }}">Sign Up</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
        @endif

        @if (request()->is('admin*') && Auth::guard('admin')->check())
            <div class="admin-layout-shell">
                @include('components.admin-sidebar')
                <main class="admin-layout-main">
                    @yield('content')
                </main>
            </div>
        @else
            <main>
                @yield('content')
            </main>
        @endif

        <!-- ============ FOOTER ============ -->
        @unless (request()->is('admin*') && Auth::guard('admin')->check())
            @include('partials.site-footer')
        @endunless
    </div>

    <!-- Scripts -->
    <script src="https://code.iconify.design/iconify-icon/2.3.0/iconify-icon.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
    @if (request()->is('admin*') && Auth::guard('admin')->check())
        <script>
            (() => {
                if (window.__legalBruzStatusDropdownsInitialized) return;
                window.__legalBruzStatusDropdownsInitialized = true;

                const statusSelects = document.querySelectorAll('select[name="status"]:not([data-status-enhanced])');

                const optionPalette = option => ({
                    bg: option.dataset.statusBg || '#ffffff',
                    border: option.dataset.statusBorder || '#d5dee8',
                    text: option.dataset.statusText || '#334155',
                    dot: option.dataset.statusDot || '#94a3b8',
                    label: option.dataset.statusLabel || option.textContent.trim(),
                    scheme: option.dataset.statusScheme || (option.value ? 'Status' : 'All workflow statuses'),
                });

                const applyPalette = (element, palette) => {
                    element.style.setProperty('--status-bg', palette.bg);
                    element.style.setProperty('--status-border', palette.border);
                    element.style.setProperty('--status-text', palette.text);
                    element.style.setProperty('--status-dot', palette.dot);
                };

                const closeStatusMenus = () => {
                    document.querySelectorAll('.admin-status-select.is-open').forEach(dropdown => {
                        dropdown.classList.remove('is-open');
                        dropdown.querySelector('.admin-status-select-toggle')?.setAttribute('aria-expanded', 'false');
                    });
                    document.querySelectorAll('.admin-status-menu-host').forEach(host => {
                        host.classList.remove('admin-status-menu-host');
                    });
                };

                statusSelects.forEach((select, selectIndex) => {
                    select.dataset.statusEnhanced = 'true';
                    select.classList.add('admin-status-native');

                    const wrapper = document.createElement('div');
                    wrapper.className = 'admin-status-select';

                    const toggle = document.createElement('button');
                    toggle.type = 'button';
                    toggle.className = 'admin-status-select-toggle';
                    toggle.setAttribute('aria-haspopup', 'listbox');
                    toggle.setAttribute('aria-expanded', 'false');

                    const selectedDot = document.createElement('i');
                    selectedDot.className = 'admin-status-select-dot';
                    selectedDot.setAttribute('aria-hidden', 'true');
                    const selectedText = document.createElement('span');
                    toggle.append(selectedDot, selectedText);

                    const menu = document.createElement('div');
                    menu.className = 'admin-status-select-menu';
                    menu.id = `adminStatusMenu${selectIndex}`;
                    menu.setAttribute('role', 'listbox');
                    toggle.setAttribute('aria-controls', menu.id);

                    const syncSelection = () => {
                        const selectedOption = select.options[select.selectedIndex] || select.options[0];
                        const palette = optionPalette(selectedOption);
                        selectedText.textContent = selectedOption.value
                            ? `${palette.label} — ${palette.scheme}`
                            : palette.label;
                        applyPalette(toggle, palette);

                        menu.querySelectorAll('.admin-status-select-option').forEach((item, index) => {
                            item.setAttribute('aria-selected', index === select.selectedIndex ? 'true' : 'false');
                        });
                    };

                    Array.from(select.options).forEach((option, optionIndex) => {
                        const palette = optionPalette(option);
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'admin-status-select-option';
                        item.setAttribute('role', 'option');
                        applyPalette(item, palette);

                        const dot = document.createElement('i');
                        dot.setAttribute('aria-hidden', 'true');
                        const copy = document.createElement('span');
                        const label = document.createElement('strong');
                        label.textContent = palette.label;
                        copy.append(label);

                        if (option.value) {
                            const scheme = document.createElement('small');
                            scheme.textContent = palette.scheme;
                            copy.append(scheme);
                        }

                        item.append(dot, copy);
                        item.addEventListener('click', event => {
                            event.preventDefault();
                            event.stopPropagation();
                            select.selectedIndex = optionIndex;
                            select.dispatchEvent(new Event('change', { bubbles: true }));
                            syncSelection();
                            closeStatusMenus();
                            toggle.focus();
                        });
                        menu.append(item);
                    });

                    toggle.addEventListener('click', event => {
                        event.preventDefault();
                        event.stopPropagation();
                        const willOpen = !wrapper.classList.contains('is-open');
                        closeStatusMenus();
                        if (willOpen) {
                            const menuHost = wrapper.closest('.card, .admin-card, .admin-quicklinks-panel')
                                || wrapper.parentElement;
                            menuHost?.classList.add('admin-status-menu-host');
                            wrapper.classList.add('is-open');
                            toggle.setAttribute('aria-expanded', 'true');
                        }
                    });
                    toggle.addEventListener('keydown', event => {
                        if (event.key === 'Escape') {
                            wrapper.classList.remove('is-open');
                            toggle.setAttribute('aria-expanded', 'false');
                        }
                    });

                    select.insertAdjacentElement('afterend', wrapper);
                    wrapper.append(toggle, menu);
                    syncSelection();
                });

                document.addEventListener('click', event => {
                    if (!event.target.closest('.admin-status-select')) closeStatusMenus();
                });

                const toggle = document.querySelector('.admin-sidebar-toggle');
                const overlay = document.querySelector('.admin-sidebar-overlay');
                const sidebarLinks = document.querySelectorAll('.admin-sidebar a');
                const closeSidebar = () => {
                    document.body.classList.remove('admin-sidebar-open');
                    toggle?.setAttribute('aria-expanded', 'false');
                };

                toggle?.addEventListener('click', () => {
                    const isOpen = document.body.classList.toggle('admin-sidebar-open');
                    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });
                overlay?.addEventListener('click', closeSidebar);
                sidebarLinks.forEach(link => link.addEventListener('click', closeSidebar));
                document.addEventListener('keydown', event => {
                    if (event.key === 'Escape') closeSidebar();
                });
            })();
        </script>
    @endif
    @include('partials.disclaimer-consent')
    @include('partials.button-loading')
    @include('partials.sweet-alert-confirmations')
</body>

</html>
