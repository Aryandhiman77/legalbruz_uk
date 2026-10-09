<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Legal Bruz Ltd. - IPR & Trademark Registration | India's Fastest Platform</title>
    <link rel="icon" type="image/png" href="{{ asset('legal-bruz-ltd-logo.png') }}">
    <meta name="description" content="Protect your brand with Legal Bruz. Search trademarks, file applications, respond to objections, and manage intellectual property matters online.">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <link rel="canonical" href="{{ route('landing') }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Legal Bruz">
    <meta property="og:title" content="Legal Bruz - Trademark & Intellectual Property Services">
    <meta property="og:description" content="Trademark registration and intellectual property support made clear, accessible, and easy to manage.">
    <meta property="og:url" content="{{ route('landing') }}">
    <meta property="og:image" content="{{ asset('legal-bruz-ltd-logo.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Legal Bruz - Trademark & Intellectual Property Services">
    <meta name="twitter:description" content="Trademark registration and intellectual property support made clear and accessible.">
    <meta name="twitter:image" content="{{ asset('legal-bruz-ltd-logo.png') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Legal Bruz',
            'url' => route('landing'),
            'logo' => asset('legal-bruz-ltd-logo.png'),
            'email' => 'info@legalbruz.com',
            'sameAs' => collect(config('social_links'))->pluck('url')->values()->all(),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => '34 Krishna Nagar',
                'addressLocality' => 'Ambala Cantt',
                'addressRegion' => 'Haryana',
                'postalCode' => '133001',
                'addressCountry' => 'IN',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @php
        $registrationGuideCssVersion = file_exists(public_path('css/RegistrationGuide.css'))
            ? filemtime(public_path('css/RegistrationGuide.css'))
            : '1';
        $homeCssVersion = file_exists(public_path('css/home.css'))
            ? filemtime(public_path('css/home.css'))
            : '1';
    @endphp
    <link rel="stylesheet" href="{{ asset('css/RegistrationGuide.css') }}?v={{ $registrationGuideCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/home.css') }}?v={{ $homeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/site-footer.css') }}?v={{ filemtime(public_path('css/site-footer.css')) }}">
    @if (!empty($searchPage))
        @vite('resources/js/trademark-probability.js')
    @endif
    @if (!empty($searchPage))
        <style>
            body.trademark-search-page .hero,
            body.trademark-search-page .trust-section,
            body.trademark-search-page .services-section,
            body.trademark-search-page .flow-section,
            body.trademark-search-page .process-section,
            body.trademark-search-page .benefits-section,
            body.trademark-search-page .pricing-section,
            body.trademark-search-page .testimonials-section,
            body.trademark-search-page .cta-section {
                display: none !important;
            }

            body.trademark-search-page .search-section {
                display: block !important;
                min-height: auto !important;
                padding: 0 !important;
                background: #eef5f6 !important;
            }

            body.trademark-search-page .search-section > .container {
                max-width: 100% !important;
                padding: 0 !important;
            }

            body.trademark-search-page .search-section .section-header,
            body.trademark-search-page .search-panel {
                display: none !important;
            }

            body.trademark-search-page .tm-results-shell {
                margin-top: 0 !important;
                width: 100% !important;
                min-height: calc(100vh - 96px) !important;
                border: 0 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
            }
        </style>
    @endif
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap"
        rel="stylesheet">

    <style>
        /* ============ FLOW DESIGN SECTION ============ */
        .flow-section {
            position: relative;
            overflow: hidden;
            padding: 92px 0 76px;
            background:
                radial-gradient(circle at 0 0, rgba(42, 157, 143, 0.14) 0 92px, transparent 93px),
                radial-gradient(circle at 100% 0, rgba(42, 157, 143, 0.12) 0 92px, transparent 93px),
                linear-gradient(180deg, #ffffff 0%, #fbfefe 100%);
        }

        .flow-section::before,
        .flow-section::after {
            content: "";
            position: absolute;
            width: 118px;
            height: 90px;
            opacity: 0.48;
            background-image: radial-gradient(circle, rgba(42, 157, 143, 0.38) 2px, transparent 3px);
            background-size: 22px 22px;
            pointer-events: none;
        }

        .flow-section::before {
            top: 24px;
            left: 26px;
        }

        .flow-section::after {
            top: 62px;
            right: 88px;
        }

        .flow-section .container {
            position: relative;
            z-index: 1;
        }

        .flow-header {
            max-width: 960px;
            margin: 0 auto 76px;
            text-align: center;
        }

        .flow-kicker {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 22px;
            margin-bottom: 22px;
            border-radius: 999px;
            background: #eaf8f6;
            color: #079987;
            font-size: 0.9rem;
            font-weight: 900;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .flow-header h2 {
            margin-bottom: 18px;
            color: var(--navy);
            font-size: 2.8rem;
            line-height: 1.02;
            letter-spacing: 0;
        }

        .flow-header h2 span {
            color: #09a895;
        }

        .flow-header p {
            margin: 0;
            color: #555a62;
            font-size: 1.12rem;
            font-weight: 500;
        }

        .flow-timeline {
            position: relative;
            display: grid;
            grid-template-columns: repeat(8, minmax(104px, 1fr));
            gap: 16px;
            margin-bottom: 52px;
        }

        .flow-timeline::before {
            content: "";
            position: absolute;
            top: 31px;
            left: 4%;
            right: 4%;
            height: 4px;
            background: linear-gradient(90deg, #0a9f91 0%, rgba(10, 159, 145, 0.18) 100%);
        }

        .flow-step {
            position: relative;
            min-width: 0;
            padding: 0;
            text-align: center;
        }

        .flow-step::before {
            content: "";
            position: absolute;
            top: 28px;
            right: -13px;
            z-index: 2;
            width: 12px;
            height: 12px;
            border: 2px solid rgba(10, 159, 145, 0.3);
            border-radius: 50%;
            background: #eefaf8;
        }

        .flow-step:last-child::before {
            display: none;
        }

        .step-circle {
            position: relative;
            z-index: 3;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 62px;
            height: 62px;
            margin-bottom: 64px;
            border: 0;
            border-radius: 50%;
            color: #ffffff;
            background:
                radial-gradient(circle at 32% 22%, rgba(255, 255, 255, 0.32), transparent 28px),
                linear-gradient(135deg, #14b8a6 0%, #078d80 100%);
            box-shadow: 0 13px 25px rgba(8, 141, 128, 0.28);
            font-size: 1.45rem;
            font-weight: 900;
            line-height: 1;
        }

        .step-circle::before,
        .step-circle::after {
            content: "";
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }

        .step-circle::before {
            bottom: -48px;
            width: 2px;
            height: 46px;
            background: rgba(10, 159, 145, 0.25);
        }

        .step-circle::after {
            bottom: -52px;
            width: 9px;
            height: 9px;
            border: 1px solid rgba(10, 159, 145, 0.26);
            border-radius: 50%;
            background: #d9f2ef;
        }

        .flow-card {
            position: relative;
            min-height: 300px;
            padding: 32px 14px 26px;
            border: 1px solid #e5eeee;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 18px 36px rgba(29, 53, 87, 0.09);
        }

        .flow-card::before {
            content: "";
            position: absolute;
            top: -21px;
            left: 50%;
            width: 42px;
            height: 42px;
            transform: translateX(-50%) rotate(45deg);
            border-top: 1px solid #e5eeee;
            border-left: 1px solid #e5eeee;
            border-radius: 6px 0 0 0;
            background: #ffffff;
        }

        .flow-icon {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 84px;
            height: 84px;
            margin-bottom: 24px;
            border-radius: 50%;
            background: #edf7f6;
            color: #079987;
            font-size: 2rem;
        }

        .flow-section .step-title {
            display: block;
            min-height: 40px;
            margin-bottom: 22px;
            color: var(--navy);
            font-size: 1rem;
            font-weight: 900;
            line-height: 1.25;
        }

        .flow-section .step-title::before {
            content: none;
        }

        .flow-section .step-description {
            min-height: 70px;
            max-width: 132px;
            margin: 0 auto;
            color: #4f545c;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .flow-action {
            position: relative;
            overflow: hidden;
            display: grid;
            place-items: center;
            min-height: 230px;
            padding: 42px 22px;
            border: 1px solid #dcefed;
            border-radius: 20px;
            background:
                radial-gradient(circle at 95% 110%, rgba(42, 157, 143, 0.15) 0 126px, transparent 127px),
                linear-gradient(135deg, #f9fefd 0%, #f4fbfa 100%);
            text-align: center;
        }

        .flow-action::before {
            content: "";
            position: absolute;
            left: 76px;
            bottom: 34px;
            width: 240px;
            height: 130px;
            background:
                linear-gradient(150deg, transparent 0 41%, #079987 42% 58%, transparent 59%),
                linear-gradient(35deg, transparent 0 40%, #0aa896 41% 58%, transparent 59%);
            clip-path: polygon(0 16%, 100% 0, 68% 76%, 47% 58%, 26% 86%);
            opacity: 0.9;
            transform: rotate(-9deg) scale(0.36);
            transform-origin: left bottom;
        }

        .flow-action::after {
            content: "";
            position: absolute;
            right: 70px;
            top: 42px;
            width: 260px;
            height: 82px;
            opacity: 0.45;
            border-top: 2px dashed rgba(10, 159, 145, 0.35);
            border-radius: 50%;
            transform: rotate(-16deg);
        }

        .flow-action-content {
            position: relative;
            z-index: 1;
            max-width: 560px;
        }

        .flow-action-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 54px;
            height: 54px;
            margin-bottom: 14px;
            border-radius: 50%;
            background: #e4f6f3;
            color: #079987;
            font-size: 1.7rem;
        }

        .flow-action h3 {
            margin-bottom: 6px;
            color: #079987;
            font-size: 1.28rem;
            font-weight: 900;
        }

        .flow-action p {
            margin-bottom: 26px;
            color: #4f545c;
            font-size: 1.02rem;
        }

        @media (max-width: 1199px) {
            .flow-timeline {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 44px 24px;
                max-width: 900px;
                margin-inline: auto;
            }

            .flow-timeline::before,
            .flow-step::before {
                display: none;
            }

            .flow-step {
                display: flex;
                flex-direction: column;
                align-items: center;
            }

            .flow-step::after {
                display: none;
            }

            .flow-step:nth-child(6) .step-circle {
                width: 62px;
                height: 62px;
                margin-top: 0;
            }

            .flow-card {
                width: 100%;
                min-height: 260px;
            }
        }

        @media (max-width: 768px) {
            .flow-section {
                padding: 56px 0 44px;
            }

            .flow-section::before,
            .flow-section::after {
                width: 82px;
                height: 64px;
                background-size: 18px 18px;
            }

            .flow-header {
                margin-bottom: 32px;
            }

            .flow-header h2 {
                font-size: clamp(1.75rem, 9vw, 2.35rem);
                line-height: 1.15;
            }

            .flow-header p {
                font-size: 0.98rem;
            }

            .flow-timeline {
                grid-template-columns: 1fr;
                gap: 18px;
                width: 100%;
                max-width: 620px;
                margin-inline: auto;
            }

            .flow-step {
                display: grid;
                grid-template-columns: 48px minmax(0, 1fr);
                gap: 14px;
                align-items: start;
                padding: 0;
                text-align: left;
            }

            .flow-step:not(:last-child)::after {
                content: "";
                position: absolute;
                top: 52px;
                bottom: -18px;
                left: 23px;
                display: block;
                width: 2px;
                height: auto;
                transform: none;
                border-radius: 999px;
                background: linear-gradient(180deg, rgba(10, 159, 145, 0.45), rgba(10, 159, 145, 0.12));
            }

            .step-circle {
                display: flex;
                width: 48px;
                height: 48px;
                margin: 0;
                border-width: 4px;
                font-size: 1.05rem;
            }

            .step-circle::before,
            .step-circle::after {
                display: none;
            }

            .flow-step:nth-child(6) .step-circle {
                display: flex;
                width: 48px;
                height: 48px;
            }

            .flow-card {
                display: grid;
                grid-template-columns: 58px minmax(0, 1fr);
                column-gap: 14px;
                row-gap: 2px;
                align-items: center;
                min-height: auto;
                padding: 16px;
                border-radius: 14px;
                text-align: left;
            }

            .flow-card::before {
                display: none;
            }

            .flow-icon {
                grid-column: 1;
                grid-row: 1 / span 2;
                width: 54px;
                height: 54px;
                margin: 0;
                font-size: 1.5rem;
            }

            .flow-section .step-title {
                grid-column: 2;
                min-height: 0;
                margin: 0 0 3px;
                align-self: end;
                text-align: left;
                font-size: 0.98rem;
            }

            .flow-section .step-description {
                grid-column: 2;
                min-height: 0;
                max-width: none;
                margin: 0;
                align-self: start;
                text-align: left;
                font-size: 0.86rem;
                line-height: 1.4;
            }

            .flow-action {
                min-height: 250px;
            }

            .flow-action::before {
                left: 18px;
                bottom: 8px;
            }

            .flow-action::after {
                right: -80px;
                top: 28px;
            }
        }

        /* Reference-style services layout */
        .services-section {
            position: relative;
            overflow: hidden;
            padding: 96px 0 104px;
            background:
                radial-gradient(circle at 0 0, rgba(29, 53, 87, 0.06) 0 170px, transparent 171px),
                radial-gradient(circle at 100% 20%, rgba(42, 157, 143, 0.11) 0 135px, transparent 136px),
                linear-gradient(180deg, #f9fbff 0%, #ffffff 100%);
        }

        .services-section::before,
        .services-section::after {
            content: "";
            position: absolute;
            width: 116px;
            height: 88px;
            opacity: 0.38;
            background-image: radial-gradient(circle, rgba(42, 157, 143, 0.42) 2px, transparent 3px);
            background-size: 18px 18px;
            pointer-events: none;
        }

        .services-section::before {
            top: 92px;
            left: 46px;
        }

        .services-section::after {
            top: 284px;
            right: 70px;
            background-image: radial-gradient(circle, rgba(136, 87, 199, 0.24) 2px, transparent 3px);
        }

        .services-section .container {
            position: relative;
            z-index: 1;
        }

        .services-section .section-header {
            max-width: 830px;
            margin-bottom: 58px;
        }

        .services-header-mark {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 16px;
            color: #079987;
        }

        .services-header-mark span {
            width: 54px;
            height: 3px;
            border-radius: 999px;
            background: linear-gradient(90deg, transparent, #079987);
        }

        .services-header-mark span:last-child {
            background: linear-gradient(90deg, #079987, transparent);
        }

        .services-header-mark i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border: 1px solid rgba(42, 157, 143, 0.22);
            border-radius: 50%;
            background: #ffffff;
            box-shadow: 0 12px 30px rgba(29, 53, 87, 0.08);
            font-size: 1.25rem;
        }

        .services-section .service-card {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 100%;
            padding: 28px 28px 30px;
            border: 1px solid rgba(42, 157, 143, 0.22);
            border-radius: 18px;
            background:
                radial-gradient(circle at 88% 13%, rgba(42, 157, 143, 0.08) 0 58px, transparent 59px),
                #ffffff;
            box-shadow: 0 20px 48px rgba(29, 53, 87, 0.08);
            transition: transform 0.35s ease, box-shadow 0.35s ease, border-color 0.35s ease;
        }

        .services-section .service-card::before {
            content: "";
            position: absolute;
            top: 18px;
            right: 18px;
            left: auto;
            width: 92px;
            height: 112px;
            opacity: 0.24;
            background: none;
            background-image: radial-gradient(circle, currentColor 2px, transparent 3px);
            background-size: 12px 12px;
            color: #079987;
            transform: none;
            pointer-events: none;
        }

        .services-section .service-card:hover::before {
            transform: none;
        }

        .services-section .service-card:hover {
            border-color: rgba(42, 157, 143, 0.34);
            transform: translateY(-8px);
            box-shadow: 0 26px 60px rgba(29, 53, 87, 0.13);
        }

        .services-section .service-card--copyright {
            border-color: rgba(58, 150, 214, 0.32);
            background:
                radial-gradient(circle at 88% 13%, rgba(58, 150, 214, 0.08) 0 58px, transparent 59px),
                #ffffff;
        }

        .services-section .service-card--copyright::before {
            color: #3a96d6;
        }

        .services-section .service-card--patent {
            border-color: rgba(136, 87, 199, 0.32);
            background:
                radial-gradient(circle at 88% 13%, rgba(136, 87, 199, 0.08) 0 58px, transparent 59px),
                #ffffff;
        }

        .services-section .service-card--patent::before {
            color: #8857c7;
        }

        .services-section .service-icon {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 98px;
            height: 98px;
            margin-bottom: 26px;
            border: 1px solid rgba(42, 157, 143, 0.34);
            border-radius: 50%;
            color: #079987;
            background: rgba(255, 255, 255, 0.72);
            font-size: 2.6rem;
            line-height: 1;
        }

        .services-section .service-icon--text {
            font-family: "Manrope", sans-serif;
            font-size: 2.15rem;
            font-weight: 900;
        }

        .services-section .service-card--copyright .service-icon {
            border-color: rgba(58, 150, 214, 0.38);
            color: #3a96d6;
        }

        .services-section .service-card--patent .service-icon {
            border-color: rgba(136, 87, 199, 0.38);
            color: #8857c7;
        }

        .services-section .service-card h4 {
            margin-bottom: 16px;
            color: var(--navy);
            font-size: 1.45rem;
            font-weight: 900;
            line-height: 1.2;
        }

        .services-section .service-card h4::after {
            content: "";
            display: block;
            width: 58px;
            height: 5px;
            margin-top: 14px;
            border-radius: 999px;
            background: #079987;
        }

        .services-section .service-card--copyright h4::after {
            background: #3a96d6;
        }

        .services-section .service-card--patent h4::after {
            background: #8857c7;
        }

        .services-section .service-card p {
            min-height: 78px;
            margin-bottom: 24px;
            color: #353b43;
            font-size: 0.98rem;
            line-height: 1.75;
        }

        .services-section .service-features {
            list-style: none;
            margin: 0 0 32px;
            padding: 26px 0 0;
            border-top: 1px solid #edf0f2;
        }

        .services-section .service-features li {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 14px 0;
            color: #242a31;
            font-size: 0.95rem;
            font-weight: 700;
        }

        .services-section .service-features li::before {
            content: "\F26E";
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #c8f3ea;
            color: #079987;
            font-family: "bootstrap-icons";
            font-weight: 900;
            font-size: 0.95rem;
            flex: 0 0 auto;
        }

        .services-section .service-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            margin-top: auto;
            padding: 15px 22px;
            border: 0;
            border-radius: 10px;
            background: linear-gradient(135deg, #10b7a5 0%, #079987 100%);
            color: #ffffff;
            box-shadow: 0 16px 34px rgba(7, 153, 135, 0.24);
            font-size: 1.05rem;
            font-weight: 900;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .services-section .service-btn:hover {
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 20px 42px rgba(7, 153, 135, 0.3);
        }

        .services-section .service-btn--offer {
            background: linear-gradient(135deg, #5546ea, #6157f7);
            box-shadow: 0 18px 38px rgba(85, 70, 234, 0.26);
        }

        .services-section .service-btn--offer:hover {
            box-shadow: 0 22px 46px rgba(85, 70, 234, 0.32);
        }

        .service-offer-badge {
            position: absolute;
            top: 30px;
            right: 32px;
            z-index: 2;
            max-width: 150px;
            padding: 0.7rem 0.85rem;
            border-radius: 14px;
            background: linear-gradient(135deg, #5546ea, #6157f7);
            color: #ffffff;
            box-shadow: 0 16px 34px rgba(85, 70, 234, 0.22);
            text-align: left;
        }

        .service-offer-badge strong {
            display: block;
            font-size: 0.9rem;
            font-weight: 950;
            letter-spacing: 0.03em;
            overflow-wrap: anywhere;
        }

        .service-offer-badge span {
            display: block;
            margin-top: 0.2rem;
            font-size: 0.72rem;
            font-weight: 800;
            line-height: 1.25;
        }

        .services-section .service-btn--disabled,
        .services-section .service-btn--disabled:hover {
            background: linear-gradient(135deg, #8b8b8b 0%, #6f6f6f 100%);
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.16);
            color: #ffffff;
            cursor: not-allowed;
            transform: none;
        }

        .services-section .service-icon--image img {
            display: block;
            width: 64px;
            height: 64px;
            object-fit: contain;
        }

        .coupon-ribbon {
            width: 100%;
            background: linear-gradient(135deg, #5546ea, #6157f7);
            color: #ffffff;
            padding: 10px 18px;
            text-align: center;
            font-weight: 850;
            letter-spacing: 0;
        }

        .coupon-ribbon__inner {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .coupon-ribbon__code {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 4px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.28);
            font-size: 0.95rem;
            line-height: 1;
        }

        .coupon-ribbon__timer {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-variant-numeric: tabular-nums;
        }

        .coupon-ribbon__timer strong {
            color: #ffffff;
            font-weight: 950;
        }

        @media (max-width: 576px) {
            .coupon-ribbon {
                padding: 9px 12px;
                font-size: 0.86rem;
            }

            .coupon-ribbon__inner {
                gap: 8px;
            }
        }
    </style>

</head>

<body class="{{ !empty($searchPage) ? 'trademark-search-page' : '' }}">
    @php
        $trademarkPricingCoupon = auth()->check()
            ? \App\Models\DiscountCoupon::autoApplyForPayment('trademark_filing', auth()->id())
            : \App\Models\DiscountCoupon::autoApplyForPublicService('trademark_filing');
        $showCouponRibbon = $trademarkPricingCoupon && $trademarkPricingCoupon->ends_at;
        $couponRibbonEndsAt = $showCouponRibbon
            ? $trademarkPricingCoupon->ends_at
                ->copy()
                ->timezone(config('app.timezone', 'Europe/London'))
                ->toIso8601String()
            : null;
    @endphp

    @if ($showCouponRibbon)
        <div class="coupon-ribbon" data-coupon-ribbon data-offer-ends-at="{{ $couponRibbonEndsAt }}">
            <div class="coupon-ribbon__inner">
                <span class="coupon-ribbon__code">{{ $trademarkPricingCoupon->code }}</span>
                <span>{{ $trademarkPricingCoupon->discount_label }} on Trademark Filing</span>
                <span class="coupon-ribbon__timer">
                    Ends in <strong data-coupon-countdown>--d : --h : --mins : --sec</strong>
                </span>
            </div>
        </div>
    @endif

    <!-- ============ NAVBAR ============ -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="/">
                <img src="{{ asset('legal-bruz-ltd-logo.png') }}" alt="Legal Bruz Ltd. logo"
                    class="navbar-logo" width="106" height="79">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item services-nav-item">
                        <a class="nav-link" href="{{ !empty($searchPage) ? route('landing') . '#services' : '#services' }}" id="servicesDropdown" role="button"
                            aria-expanded="false">
                            Services
                        </a>
                        <div class="services-mega-menu" aria-labelledby="servicesDropdown">
                            <div class="services-mega-panel">
                                <div class="services-mega-sidebar">
                                    <p class="services-mega-kicker">Trademark</p>
                                    <a class="services-category-item is-active" href="{{ !empty($searchPage) ? route('landing') . '#services' : '#services' }}">
                                        Trademark Services
                                    </a>
                                </div>
                                <div class="services-mega-content">
                                    <p class="services-mega-heading">Trademark services</p>
                                    <div class="services-card-grid">
                                        <a class="services-card" href="{{ auth()->check() ? route('trademark.type-selection') : route('register') }}">
                                            <div class="services-card-head">
                                                <span class="services-card-icon"><i class="bi bi-award"></i></span>
                                                <span class="services-card-arrow"><i class="bi bi-arrow-right"></i></span>
                                            </div>
                                            <h3 class="services-card-title">File Trademark</h3>
                                            <p class="services-card-text">Protect your brand name, logo, and slogan with our full trademark filing service.</p>
                                        </a>
                                    </div>
                                    <div class="services-mega-footer">
                                        <i class="bi bi-info-circle"></i>
                                        <span>Need help choosing the right trademark service?</span>
                                        <span class="services-footer-divider"></span>
                                        <strong>Contact our team</strong>
                                        <span class="services-footer-dot"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ !empty($searchPage) ? route('landing') . '#why-us' : '#why-us' }}">Why Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ !empty($searchPage) ? route('landing') . '#pricing' : '#pricing' }}">Pricing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('trademark.search-page', [], false) }}">Search</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ !empty($searchPage) ? route('landing') . '#testimonials' : '#testimonials' }}">Reviews</a>
                    </li>
                    <li class="nav-item ms-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn-nav-primary">Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="btn-nav-login">Login</a>
                            <a href="{{ route('register') }}" class="btn-nav-primary ms-2">Sign Up</a>
                        @endauth
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="offcanvas offcanvas-start" tabindex="-1" id="servicesOffcanvas" aria-labelledby="servicesOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="servicesOffcanvasLabel">Services</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="mb-3">
                <h6 class="mb-2">Trademark</h6>
                <div class="services-offcanvas-box">
                    <a href="{{ auth()->check() ? route('trademark.type-selection') : route('register') }}" class="services-offcanvas-item">
                        <h3 class="services-offcanvas-title">File Trademark</h3>
                        <p class="services-offcanvas-text">Protect your brand name, logo, and slogan with our full trademark filing service.</p>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <style>
        @media (max-width: 991px) {
            .navbar-nav .dropdown-toggle::after {
                display: none !important;
            }
        }
    </style>

    <!-- ============ HERO SECTION ============ -->
    <section class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <h1>Start Your <span>Trademark Registration</span> in Minutes.</h1>
                    <p class="hero-subtitle">
                        Expert assisted Trademark, Copyright, Patent services with transparent pricing & hassle-free
                        filing.
                    </p>
                    <!-- <p class="hero-subtitle">
                        India's fastest IPR platform. File Trademark, Copyright & Patent with expert guidance.
                        Get approved 50% faster with our hassle-free process.
                    </p> -->

                    <div class="hero-cta">
                        @auth
                            <a href="{{ route('trademark.type-selection') }}" class="btn-hero btn-hero-primary">
                                <i class="bi bi-arrow-right" style="margin-right: 8px;"></i>Start Registration
                            </a>
                        @else
                            <a href="{{ route('register') }}" class="btn-hero btn-hero-primary">
                                <i class="bi bi-arrow-right" style="margin-right: 8px;"></i>Get Started Free
                            </a>
                        @endauth
                        <a href="#flow" class="btn-hero btn-hero-secondary">
                            <i class="bi bi-play-circle" style="margin-right: 8px;"></i>See How It Works
                        </a>
                    </div>

                    <div class="hero-stats">
                        <div class="stat">
                            <div class="stat-number">50K+</div>
                            <div class="stat-label">Applications Filed</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">98%</div>
                            <div class="stat-label">Success Rate</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">₹{{ number_format(($trademarkPricingPlans ?? \App\Models\TrademarkPricing::activePlans())['individual']['amount'] ?? \App\Models\TrademarkPricing::defaults()['individual']['amount'], 0) }}</div>
                            <div class="stat-label">Starting Price</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 text-center d-none d-lg-block">
                    <div style="font-size: 200px; color: rgba(255,255,255,0.12); line-height: 1;">
                        <i class="bi bi-shield-check"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ TRUST BADGES ============ -->
    <section class="trust-section">
        <div class="container">
            <div class="trust-content">
                <!-- <div class="trust-item">
                    <div class="trust-icon">
                        <i class="bi bi-check2"></i>
                    </div>
                    <div>
                        <div style="font-weight: 800; color: var(--navy);">Govt Verified</div>
                        <div style="font-size: 0.85rem;">Filed with IPO</div>
                    </div>
                </div> -->
                <div class="trust-item">
                    <div class="trust-icon">
                        <i class="bi bi-clock"></i>
                    </div>
                    <div>
                        <div style="font-weight: 800; color: var(--navy);">48 Hour Filing</div>
                        <div style="font-size: 0.85rem;">Quick processing</div>
                    </div>
                </div>
                <div class="trust-item">
                    <div class="trust-icon">
                        <i class="bi bi-chat-dots"></i>
                    </div>
                    <div>
                        <div style="font-weight: 800; color: var(--navy);">24/7 Support</div>
                        <div style="font-size: 0.85rem;">Expert guidance</div>
                    </div>
                </div>
                <div class="trust-item">
                    <div class="trust-icon">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <div>
                        <div style="font-weight: 800; color: var(--navy);">100% Secure</div>
                        <div style="font-size: 0.85rem;">Data protected</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ SERVICES SECTION ============ -->
    <section class="services-section" id="services">
        @php
            $trademarkPricingCoupon = $trademarkPricingCoupon ?? (
                auth()->check()
                    ? \App\Models\DiscountCoupon::autoApplyForPayment('trademark_filing', auth()->id())
                    : \App\Models\DiscountCoupon::autoApplyForPublicService('trademark_filing')
            );
        @endphp
        <div class="container">
            <div class="section-header">
                <div class="services-header-mark">
                    <span></span>
                    <i class="bi bi-shield-fill-check"></i>
                    <span></span>
                </div>
                <h2>Our Services</h2>
                <p>Complete IPR protection solutions tailored for every business size and need</p>
            </div>

            <div class="row g-4">
                <!-- Trademark -->
                <div class="col-md-6 col-lg-4">
                    <div class="service-card service-card--trademark">
                        @if ($trademarkPricingCoupon)
                            <div class="service-offer-badge">
                                <strong>{{ $trademarkPricingCoupon->code }}</strong>
                                <span>{{ $trademarkPricingCoupon->discount_label }}</span>
                            </div>
                        @endif
                        <div class="service-icon service-icon--text">TM</div>
                        <h4>Trademark Registration</h4>
                        <p>Protect your brand name, logo, and slogan with our comprehensive registration service.</p>
                        <ul class="service-features">
                            <li>Trademark Search & Report</li>
                            <li>Application Filing</li>
                            <!-- <li>Opposition Reply</li> -->
                            <li>10 Year Protection</li>
                            <li>Renewal Support</li>
                        </ul>
                        @auth
                            <a href="{{ route('trademark.type-selection') }}" class="service-btn {{ $trademarkPricingCoupon ? 'service-btn--offer' : '' }}">
                                <i class="bi bi-arrow-right-circle-fill"></i>
                                <span>{{ $trademarkPricingCoupon ? 'Claim Offer' : 'Apply Now' }}</span>
                            </a>
                        @else
                            <a href="{{ route('register') }}" class="service-btn {{ $trademarkPricingCoupon ? 'service-btn--offer' : '' }}">
                                <i class="bi bi-arrow-right-circle-fill"></i>
                                <span>{{ $trademarkPricingCoupon ? 'Claim Offer' : 'Get Started' }}</span>
                            </a>
                        @endauth
                    </div>
                </div>

                <!-- Copyright -->
                <div class="col-md-6 col-lg-4">
                    <div class="service-card service-card--copyright">
                        <div class="service-icon"><i class="bi bi-c-circle"></i></div>
                        <h4>Copyright Registration</h4>
                        <p>Safeguard your creative works - music, art, literature, software, and designs.</p>
                        <ul class="service-features">
                            <li>Instant Registration</li>
                            <li>Certificate Generation</li>
                            <li>Infringement Support</li>
                            <li>Digital Archive</li>
                        </ul>
                        <a href="{{ route('contact') }}" class="service-btn">
                            <i class="bi bi-chat-dots-fill"></i>
                            <span>Contact Us</span>
                        </a>
                    </div>
                </div>

                <!-- Patent -->
                <div class="col-md-6 col-lg-4">
                    <div class="service-card service-card--patent">
                        <div class="service-icon service-icon--image" aria-hidden="true">
                            <img src="https://api.iconify.design/noto/microscope.svg" alt="">
                        </div>
                        <h4>Patent Registration</h4>
                        <p>Secure your innovations with provisional and complete patent protection.</p>
                        <ul class="service-features">
                            <li>Prior Art Search</li>
                            <li>Provisional Filing</li>
                            <li>Complete Application</li>
                            <li>Expert Review</li>
                            <li>20 Year Protection</li>
                        </ul>
                        <a href="{{ route('contact') }}" class="service-btn">
                            <i class="bi bi-chat-dots-fill"></i>
                            <span>Contact Us</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ TRADEMARK SEARCH SECTION ============ -->
    <section class="search-section" id="search">
        <div class="container">
            <div class="section-header">
                <h2>Trademark Search</h2>
                <p>Search live trademark data before you file your brand application</p>
            </div>

            <div class="search-panel">
                <div class="search-copy">
                    <span class="search-kicker">Live Conflict Check</span>
                    <h3>Start your conflict check before filing</h3>
                    <p>
                        Enter your keyword here and we’ll load matching trademark records directly into a searchable
                        review dashboard.
                    </p>
                    <ul class="search-benefits">
                        <li>Type your brand keyword here first</li>
                        <li>Review matching trademarks without leaving this page</li>
                        <li>Filter by status, class, type, and application date</li>
                    </ul>
                </div>

                <div class="search-tool">
                    <div class="search-browser">
                        <div class="search-browser-bar">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                        <form class="search-browser-body" id="tm-entry-form" action="{{ route('trademark.search-page', [], false) }}" method="GET">
                            <label for="tm-search-keyword" class="search-label">Brand / Trademark Keyword</label>
                            <input type="text" id="tm-search-keyword" name="keyword" class="search-input"
                                placeholder="Enter brand name, wordmark, or search keyword">

                            <div class="search-actions">
                                <button type="submit" class="search-btn-primary" id="tm-search-submit">
                                    <i class="bi bi-search" style="margin-right: 8px;"></i>Search Trademark
                                </button>
                                <button type="button" class="search-btn-secondary" id="tm-search-reset">
                                    Clear
                                </button>
                            </div>

                            <p class="search-note" id="tm-search-note">
                                Search results will open below with filters and sorting.
                            </p>
                        </form>
                    </div>
                </div>
            </div>

            @if (!empty($searchPage))
                <div class="tm-results-shell" id="tm-results-shell" hidden>
                    <div class="tm-results-header">
                        <div>
                            <h3>Trademark Search Dashboard</h3>
                            <p>Review matching trademark records with filters, sorting, and AI probability.</p>
                        </div>
                        <button type="button" class="tm-save-search" id="tm-save-search">
                            <i class="bi bi-bookmark"></i><span>Save Search</span>
                        </button>
                    </div>

                    <div class="tm-search-strip">
                        <div class="tm-search-type">Keyword <i class="bi bi-chevron-down"></i></div>
                        <input type="text" id="tm-results-keyword" placeholder="Search trademark keyword">
                        <button type="button" class="tm-clear-inline" id="tm-clear-inline" aria-label="Clear keyword">
                            <i class="bi bi-x-lg"></i>
                        </button>
                        <button type="button" class="tm-strip-submit" id="tm-strip-submit">
                            <i class="bi bi-search"></i><span>Search</span>
                        </button>
                        <button type="button" class="tm-strip-submit tm-ai-probability-button" id="tm-ai-probability" disabled>
                            <i class="bi bi-stars"></i><span>AI Probability</span>
                        </button>
                    </div>

                    <div class="tm-results-grid">
                        <button type="button" class="tm-mobile-filter-trigger" id="tm-mobile-filter-trigger">
                            <i class="bi bi-sliders"></i>
                            <span>Refine Search</span>
                        </button>
                        <aside class="tm-refine-panel" id="tm-refine-panel">
                            <button type="button" class="tm-mobile-filter-backdrop" id="tm-mobile-filter-backdrop" aria-label="Close filters"></button>
                            <div class="tm-sheet-grab" id="tm-sheet-grab"></div>
                            <div class="tm-filter-sheet-content" id="tm-filter-sheet-content">
                                <div class="tm-refine-head" id="tm-refine-head">
                                    <h4>Refine Search</h4>
                                    <div class="tm-refine-head-actions">
                                        <button type="button" id="tm-clear-filters">Clear</button>
                                        <button type="button" class="tm-mobile-panel-close" id="tm-mobile-panel-close" aria-label="Close filters">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="tm-filter-group">
                                    <button type="button" class="tm-filter-title">Status <i class="bi bi-chevron-up"></i></button>
                                    <div class="tm-filter-options" id="tm-status-options"></div>
                                </div>

                                <div class="tm-filter-group">
                                    <button type="button" class="tm-filter-title">Class <i class="bi bi-chevron-up"></i></button>
                                    <label class="tm-class-search">
                                        <i class="bi bi-search"></i>
                                        <input type="text" id="tm-class-search" placeholder="Search class">
                                    </label>
                                    <div class="tm-filter-options" id="tm-class-options"></div>
                                    <button type="button" class="tm-show-more" id="tm-class-show-more" hidden>Show more</button>
                                </div>

                                <div class="tm-filter-group">
                                    <button type="button" class="tm-filter-title">Type <i class="bi bi-chevron-up"></i></button>
                                    <div class="tm-filter-options" id="tm-type-options"></div>
                                </div>

                                <div class="tm-filter-group">
                                    <button type="button" class="tm-filter-title">Application Date <i class="bi bi-chevron-up"></i></button>
                                    <div class="tm-filter-options">
                                        <label class="tm-radio-row"><input type="radio" name="tm-date-filter" value="anytime" checked><span>Anytime</span></label>
                                        <label class="tm-radio-row"><input type="radio" name="tm-date-filter" value="3m"><span>Last 3 months</span></label>
                                        <label class="tm-radio-row"><input type="radio" name="tm-date-filter" value="6m"><span>Last 6 months</span></label>
                                        <label class="tm-radio-row"><input type="radio" name="tm-date-filter" value="1y"><span>Last 1 year</span></label>
                                        <label class="tm-radio-row"><input type="radio" name="tm-date-filter" value="custom"><span>Custom range</span></label>
                                    </div>
                                    <div class="tm-date-range">
                                        <input type="date" id="tm-date-from" aria-label="Date from">
                                        <span>to</span>
                                        <input type="date" id="tm-date-to" aria-label="Date to">
                                    </div>
                                </div>

                                <button type="button" class="tm-apply-filters" id="tm-apply-filters">Apply Filters</button>
                            </div>
                        </aside>

                        <div class="tm-results-main">
                            <div class="tm-results-toolbar">
                                <div>
                                    <h4 id="tm-results-title">Search Results</h4>
                                    <p id="tm-results-summary">Showing 0 results</p>
                                </div>
                                <label class="tm-sort-control">
                                    Sort
                                    <select id="tm-sort-select">
                                        <option value="relevance">Relevance</option>
                                        <option value="newest">Newest</option>
                                        <option value="oldest">Oldest</option>
                                        <option value="status">Status</option>
                                        <option value="class">Class</option>
                                    </select>
                                </label>
                            </div>

                            <div class="tm-stat-row">
                                <div class="tm-stat-card tm-stat-blue"><span>Total Results</span><strong id="tm-stat-total">0</strong><i class="bi bi-search"></i></div>
                                <div class="tm-stat-card tm-stat-green"><span>Registered</span><strong id="tm-stat-registered">0</strong><i class="bi bi-patch-check"></i></div>
                                <div class="tm-stat-card tm-stat-purple"><span>Classes Found</span><strong id="tm-stat-classes">0</strong><i class="bi bi-grid"></i></div>
                                <div class="tm-stat-card tm-stat-orange"><span>Latest Date</span><strong id="tm-stat-updated">-</strong><i class="bi bi-calendar2-week"></i></div>
                            </div>

                            <div class="tm-loading" id="tm-loading" hidden>
                                <div class="spinner-border text-success" role="status"></div>
                                <strong>Searching trademark records...</strong>
                            </div>
                            <div class="tm-empty-state" id="tm-empty-state" hidden>
                                <i class="bi bi-search-heart"></i>
                                <h5>No records found</h5>
                                <p>Try a shorter keyword or a different brand spelling.</p>
                            </div>
                            <div class="tm-card-list" id="tm-card-list"></div>

                            <div class="tm-pagination-bar">
                                <label class="tm-rows-control">
                                    Rows
                                    <select id="tm-rows-select">
                                        <option value="5">5</option>
                                        <option value="10" selected>10</option>
                                        <option value="20">20</option>
                                    </select>
                                </label>
                                <div class="tm-pager">
                                    <button type="button" id="tm-prev-page" aria-label="Previous page"><i class="bi bi-chevron-left"></i></button>
                                    <span id="tm-page-indicator">1</span>
                                    <button type="button" id="tm-next-page" aria-label="Next page"><i class="bi bi-chevron-right"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </section>

    @if (!empty($searchPage))
    <div class="modal fade tm-probability-modal" id="tm-probability-modal" tabindex="-1"
        aria-labelledby="tm-probability-title" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <span class="tm-probability-kicker"><i class="bi bi-stars"></i> Explainable analysis</span>
                        <h2 class="modal-title" id="tm-probability-title">Trademark Registration Probability</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="tm-probability-controls">
                        <div>
                            <span>Searched trademark</span>
                            <strong id="tm-probability-keyword">—</strong>
                        </div>
                        <div class="tm-probability-actions">
                            <button type="button" id="tm-probability-download" class="btn btn-success" disabled>
                                <i class="bi bi-download"></i> Download Report
                            </button>
                            <button type="button" id="tm-probability-rerun" class="btn btn-outline-success">
                                Re-run analysis
                            </button>
                        </div>
                    </div>

                    <div class="tm-probability-loading" id="tm-probability-loading" hidden>
                        <div class="spinner-border text-success" role="status"></div>
                        <strong>Analyzing trademark data...</strong>
                    </div>
                    <div class="alert alert-danger" id="tm-probability-error" role="alert" hidden></div>

                    <div id="tm-probability-content" hidden>
                        <div class="tm-probability-summary">
                            <div class="tm-probability-score is-registration">
                                <span>Registration Chance</span>
                                <strong id="tm-registration-chance">0%</strong>
                            </div>
                            <div class="tm-probability-score is-conflict">
                                <span>Conflict Risk</span>
                                <strong id="tm-conflict-risk">0%</strong>
                            </div>
                            <div class="tm-probability-level">
                                <span>Risk level</span>
                                <strong id="tm-risk-level">—</strong>
                            </div>
                        </div>
                        <section class="tm-ai-summary-card">
                            <h3><i class="bi bi-stars"></i> AI Analysis Summary <span id="tm-analysis-quality"></span></h3>
                            <p id="tm-ai-summary"></p>
                        </section>

                        <div class="tm-probability-charts">
                            <div class="tm-chart-card" id="tm-probability-overview-card"><h3>Probability overview</h3><canvas id="tm-probability-doughnut"></canvas></div>
                            <div class="tm-chart-card"><h3>Risk factors</h3><canvas id="tm-probability-factors"></canvas></div>
                        </div>

                        <div class="tm-probability-counts">
                            <div><span>Exact active Word marks</span><strong id="tm-count-word">0</strong></div>
                            <div><span>Exact active Device marks</span><strong id="tm-count-device">0</strong></div>
                            <div><span>Similar active marks</span><strong id="tm-count-similar">0</strong></div>
                            <div><span>Active classes found</span><strong id="tm-count-class">0</strong></div>
                        </div>

                        <div class="tm-probability-details" id="tm-probability-details">
                            <section><h3><i class="bi bi-lightbulb"></i> Reasons</h3><ul id="tm-probability-reasons"></ul></section>
                            <section id="tm-warnings-section" hidden><h3><i class="bi bi-exclamation-triangle"></i> Warnings</h3><ul id="tm-probability-warnings"></ul></section>
                        </div>
                        <p class="tm-probability-disclaimer" id="tm-probability-disclaimer"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- ============ QUICK FLOW TIMELINE ============ -->
    <section class="flow-section" id="flow">
        <div class="container">
            <div class="flow-header">
                <div class="flow-kicker">
                    <i class="bi bi-stars"></i>
                    <span>8 Simple Steps</span>
                </div>
                <h2><span>8-Step</span> Registration Journey</h2>
                <p>See how your trademark moves from application to registration</p>
            </div>

            <div class="flow-timeline">
                <div class="flow-step">
                    <div class="step-circle">1</div>
                    <div class="flow-card">
                        <div class="flow-icon"><i class="bi bi-person-plus"></i></div>
                        <div class="step-title">Sign Up</div>
                        <div class="step-description">Create account & receive welcome email</div>

                    </div>
                </div>
                <div class="flow-step">
                    <div class="step-circle">2</div>
                    <div class="flow-card">
                        <div class="flow-icon"><i class="bi bi-clipboard-check"></i></div>
                        <div class="step-title">Choose Type</div>
                        <div class="step-description">Individual, Company, or LLP</div>

                    </div>
                </div>
                <div class="flow-step">
                    <div class="step-circle">3</div>
                    <div class="flow-card">
                        <div class="flow-icon"><i class="bi bi-file-earmark-spreadsheet"></i></div>
                        <div class="step-title">Payment & Docs</div>
                        <div class="step-description">Pay 50% + view requirements</div>

                    </div>
                </div>
                <div class="flow-step">
                    <div class="step-circle">4</div>
                    <div class="flow-card">
                        <div class="flow-icon"><i class="bi bi-pencil-square"></i></div>
                        <div class="step-title">Fill Form</div>
                        <div class="step-description">Complete application details</div>

                    </div>
                </div>
                <div class="flow-step">
                    <div class="step-circle">5</div>
                    <div class="flow-card">
                        <div class="flow-icon"><i class="bi bi-file-earmark-check"></i></div>
                        <div class="step-title">Generate Docs</div>
                        <div class="step-description">Affidavit & POA created</div>

                    </div>
                </div>
                <div class="flow-step">
                    <div class="step-circle">6</div>
                    <div class="flow-card">
                        <div class="flow-icon"><i class="bi bi-shield-check"></i></div>
                        <div class="step-title">Admin Review</div>
                        <div class="step-description">Check & filing by admin</div>

                    </div>
                </div>
                <div class="flow-step">
                    <div class="step-circle">7</div>
                    <div class="flow-card">
                        <div class="flow-icon"><i class="bi bi-cloud-arrow-down"></i></div>
                        <div class="step-title">Download</div>
                        <div class="step-description">Get application document</div>

                    </div>
                </div>
                <div class="flow-step">
                    <div class="step-circle">8</div>
                    <div class="flow-card">
                        <div class="flow-icon"><i class="bi bi-bar-chart-line"></i></div>
                        <div class="step-title">Track Status</div>
                        <div class="step-description">Monitor in dashboard</div>

                    </div>
                </div>
            </div>

            <div class="flow-action">
                <div class="flow-action-content">
                    <div class="flow-action-icon"><i class="bi bi-shield-check"></i></div>
                    <h3>Simple. Transparent. Hassle-free.</h3>
                    <p>From application to registration, we make it easy.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ BENEFITS SECTION ============ -->
    <section class="benefits-section" id="why-us">
        <div class="container">
            <div class="section-header">
                <h2>Why Choose Legal Bruz Ltd.?</h2>
                <p>We make trademark registration simple and affordable.</p>
            </div>

            <div class="benefits-grid">
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="bi bi-lightning-charge"></i></div>
                    <h5>Lightning Fast</h5>
                    <p>Get processed your Trademark application within 48 hours of document submission with zero delays
                    </p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="bi bi-patch-check"></i></div>
                    <h5>100% Legal</h5>
                    <p>All documents prepared by certified legal professionals with expertise</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="bi bi-cash-coin"></i></div>
                    <h5>Transparent Pricing</h5>
                    <p>Simple flat pricing with 50% upfront. No hidden charges ever</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="bi bi-phone"></i></div>
                    <h5>24/7 Support</h5>
                    <p>Chat, email, or phone. Our experts are always available to help</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="bi bi-bar-chart-fill"></i></div>
                    <h5>Live Tracking</h5>
                    <p>Monitor your application status in real-time from your dashboard</p>
                </div>
                <!-- <div class="benefit-card">
                    <div class="benefit-icon">🎯</div>
                    <h5>Guaranteed Success</h5>
                    <p>98% success rate with expert guidance at every step</p>
                </div> -->
            </div>
        </div>
    </section>

    <!-- ============ PRICING SECTION ============ -->
    <section class="pricing-section" id="pricing">
        @php
            $trademarkPricingCoupon = auth()->check()
                ? \App\Models\DiscountCoupon::autoApplyForPayment('trademark_filing', auth()->id())
                : \App\Models\DiscountCoupon::autoApplyForPublicService('trademark_filing');
            $trademarkPricingPlans = $trademarkPricingPlans ?? \App\Models\TrademarkPricing::activePlans();
            $trademarkPricingDefaults = \App\Models\TrademarkPricing::defaults();
            $individualOriginalPrice = (float) ($trademarkPricingPlans['individual']['amount'] ?? $trademarkPricingDefaults['individual']['amount']);
            $companyOriginalPrice = (float) ($trademarkPricingPlans['company']['amount'] ?? $trademarkPricingDefaults['company']['amount']);
            $individualDiscountedPrice = $trademarkPricingCoupon
                ? $trademarkPricingCoupon->discountedAmountFor($individualOriginalPrice)
                : $individualOriginalPrice;
            $companyDiscountedPrice = $trademarkPricingCoupon
                ? $trademarkPricingCoupon->discountedAmountFor($companyOriginalPrice)
                : $companyOriginalPrice;
            $showTrademarkPricingDiscount = $trademarkPricingCoupon
                && ($individualDiscountedPrice < $individualOriginalPrice || $companyDiscountedPrice < $companyOriginalPrice);
        @endphp
        <style>
            .pricing-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 24px;
                align-items: stretch;
            }

            .pricing-card {
                overflow: hidden;
                min-height: 500px;
                padding: 62px 30px 30px;
                border: 2px solid #e7e9f0;
                border-radius: 14px;
                box-shadow: 0 14px 34px rgba(29, 53, 87, 0.07);
                justify-content: flex-start;
            }

            .pricing-card h4 {
                color: var(--navy);
                font-size: 1.5rem;
                line-height: 1.15;
                margin-bottom: 8px;
            }

            .pricing-description {
                color: #333842;
                font-size: 0.92rem;
                margin-bottom: 6px;
            }

            .pricing-card.has-offer {
                border-color: #deddf0;
                box-shadow: 0 16px 38px rgba(29, 53, 87, 0.08);
            }

            .pricing-card.featured {
                padding-top: 74px;
                border-color: var(--emerald);
                box-shadow: 0 18px 48px rgba(42, 157, 143, 0.15);
                transform: none;
            }

            .pricing-offer-ribbon {
                position: absolute;
                top: 23px;
                left: -42px;
                z-index: 3;
                width: 160px;
                padding: 8px 0;
                background: linear-gradient(135deg, var(--emerald) 0%, #0b8f7e 100%);
                color: #ffffff;
                box-shadow: 0 10px 22px rgba(42, 157, 143, 0.2);
                font-size: 0.72rem;
                font-weight: 950;
                letter-spacing: 0.08em;
                line-height: 1;
                text-align: center;
                text-transform: uppercase;
                transform: rotate(-47deg);
            }

            .pricing-card.featured .pricing-offer-ribbon {
                left: auto;
                right: -42px;
                background: linear-gradient(135deg, #5546ea, #6157f7);
                box-shadow: 0 10px 22px rgba(85, 70, 234, 0.2);
                transform: rotate(47deg);
            }

            .pricing-badge {
                position: absolute;
                top: 20px;
                left: 58px;
                right: 58px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
                margin: 0;
                min-height: 42px;
                padding: 9px 16px;
                border-radius: 8px;
                background: linear-gradient(135deg, var(--emerald) 0%, #228974 100%);
                box-shadow: 0 15px 28px rgba(6, 85, 73, 0.22);
                font-size: 0.66rem;
                letter-spacing: 0.12em;
                overflow: visible;
            }

            .pricing-badge::before,
            .pricing-badge::after {
                content: "";
                position: absolute;
                top: 10px;
                z-index: -1;
                width: 34px;
                height: 38px;
                background: linear-gradient(135deg, #0b665b 0%, #0d7c70 100%);
            }

            .pricing-badge::before {
                left: -26px;
                clip-path: polygon(0 0, 100% 0, 78% 50%, 100% 100%, 0 100%, 24% 50%);
            }

            .pricing-badge::after {
                right: -26px;
                clip-path: polygon(0 0, 100% 0, 76% 50%, 100% 100%, 0 100%, 22% 50%);
            }

            .pricing-badge i {
                font-size: 0.68rem;
                color: #ffffff;
                filter: drop-shadow(0 2px 5px rgba(0, 0, 0, 0.18));
            }

            .pricing-amount-stack {
                display: flex;
                flex-direction: column;
                align-items: baseline;
                justify-content: center;
                gap: 0.28rem;
                min-height: 76px;
                margin: 0.15rem 0 0.75rem;
            }

            .pricing-original-price {
                background: #6b6f78;
                -webkit-background-clip: text;
                background-clip: text;
                color: transparent;
                font-size: 1.16rem;
                font-weight: 500;
                position: relative;
                white-space: nowrap;
                opacity: 0.78;
            }

            .pricing-original-price::after {
                content: "";
                position: absolute;
                left: -4px;
                right: -4px;
                top: 50%;
                height: 3px;
                border-radius: 999px;
                background: #6b6f78;
                transform: rotate(-5deg);
            }

            .pricing-new-price {
                color: #5546ea;
                font-size: 2.75rem;
                font-weight: 950;
                line-height: 1;
                white-space: nowrap;
                text-shadow: 0 10px 26px rgba(85, 70, 234, 0.12);
            }

            .pricing-card .pricing-amount-stack {
                align-items: center;
            }

            .pricing-amount {
                margin: 0.25rem 0 0.75rem;
                color: var(--emerald);
                font-size: 2.9rem;
                text-shadow: 0 8px 22px rgba(42, 157, 143, 0.12);
            }

            .pricing-card:nth-child(3) .pricing-amount {
                background: linear-gradient(135deg, var(--emerald) 0%, #228974 100%);
                -webkit-background-clip: text;
                background-clip: text;
                color: transparent;
            }

            .pricing-period {
                margin-bottom: 10px;
                color: #333842;
                font-size: 0.9rem;
            }

            .pricing-features {
                position: relative;
                width: 100%;
                margin: 16px 0 0;
                padding: 26px 0 0;
            }

            .pricing-features::before {
                content: "";
                position: absolute;
                top: 0;
                left: 8%;
                right: 8%;
                height: 1px;
                background: linear-gradient(90deg, transparent, #e2e5ec 28%, #e2e5ec 72%, transparent);
            }

            .pricing-features::after {
                content: "";
                position: absolute;
                top: -5px;
                left: 50%;
                width: 10px;
                height: 10px;
                border-radius: 50%;
                background: #d9dce6;
                transform: translateX(-50%);
            }

            .pricing-features li {
                margin: 9px 0;
                color: #3c3f45;
                font-size: 0.9rem;
                font-weight: 500;
                gap: 12px;
            }

            .pricing-features li::before {
                color: var(--emerald);
                font-size: 1.18rem;
            }

            .pricing-btn {
                margin-top: auto;
                padding: 12px 22px;
                border-radius: 8px;
                background: linear-gradient(135deg, var(--emerald) 0%, #228974 100%);
                box-shadow: 0 15px 34px rgba(42, 157, 143, 0.2);
                font-size: 0.94rem;
                font-weight: 850;
            }

            .pricing-card:nth-child(3) .pricing-btn {
                background: linear-gradient(135deg, var(--navy) 0%, #12294b 100%) !important;
                box-shadow: 0 15px 34px rgba(29, 53, 87, 0.2);
            }

            @media (max-width: 1200px) {
                .pricing-grid {
                    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
                }
            }

            @media (max-width: 768px) {
                .pricing-card,
                .pricing-card.featured {
                    padding-inline: 28px;
                    transform: none;
                }

                .pricing-badge {
                    left: 52px;
                    right: 52px;
                }

                .pricing-new-price {
                    font-size: 3.1rem;
                }
            }

        </style>
        <div class="container">
            <div class="section-header">
                <h2>Simple, Transparent Pricing</h2>
                <p>Choose the perfect plan for your business needs</p>
            </div>

            <div class="pricing-grid">
                <div class="pricing-card {{ $showTrademarkPricingDiscount ? 'has-offer' : '' }}">
                    @if ($showTrademarkPricingDiscount)
                        <div class="pricing-offer-ribbon">{{ $trademarkPricingCoupon->discount_label }}</div>
                    @endif
                    <h4>Individual</h4>
                    <p class="pricing-description">Perfect for startups and freelancers</p>
                    @if ($showTrademarkPricingDiscount)
                        <div class="pricing-amount-stack">
                            <span class="pricing-original-price">₹{{ number_format($individualOriginalPrice, 0) }}</span>
                            <span class="pricing-new-price">₹{{ number_format($individualDiscountedPrice, 0) }}</span>
                        </div>
                    @else
                        <div class="pricing-amount">₹{{ number_format($individualOriginalPrice, 0) }}</div>
                    @endif
                    <!-- <p class="pricing-period">50% Advance • +18% GST</p>/ -->
                    <ul class="pricing-features">
                        <!-- <li>KYC Verification</li> -->
                        <li>Multi Class Filing</li>
                        <li>Documents Prepared</li>
                        <li>Admin Review</li>
                        <li>Email Support</li>
                        <!-- <li>Regular updates</li> -->
                    </ul>
                    @auth
                        <a href="{{ route('trademark.type-selection') }}" class="pricing-btn">Apply Now</a>
                    @else
                        <a href="{{ route('register') }}" class="pricing-btn">Get Started</a>
                    @endauth
                </div>

                <div class="pricing-card featured {{ $showTrademarkPricingDiscount ? 'has-offer' : '' }}">
                    <div class="pricing-badge">
                        <i class="bi bi-star-fill"></i>
                        <span>Most Popular</span>
                        <i class="bi bi-star-fill"></i>
                    </div>
                    @if ($showTrademarkPricingDiscount)
                        <div class="pricing-offer-ribbon">{{ $trademarkPricingCoupon->discount_label }}</div>
                    @endif
                    <h4>Company<span style="font-weight:200;font-size:30px">/</span> LLP <span
                            style="font-weight:200;font-size:30px">/</span> Partnership <span
                            style="font-weight:200;font-size:30px">/</span> NGO</h4>
                    <p class="pricing-description">Best for established businesses</p>
                    @if ($showTrademarkPricingDiscount)
                        <div class="pricing-amount-stack">
                            <span class="pricing-original-price">₹{{ number_format($companyOriginalPrice, 0) }}</span>
                            <span class="pricing-new-price">₹{{ number_format($companyDiscountedPrice, 0) }}</span>
                        </div>
                    @else
                        <div class="pricing-amount">₹{{ number_format($companyOriginalPrice, 0) }}</div>
                    @endif
                    <!-- <p class="pricing-period">50% Advance • +18% GST</p> -->
                    <ul class="pricing-features">
                        <li>Multi-Class Filing</li>
                        <li>Affidavit & POA</li>
                        <li>Documents Prepared</li>
                        <li>Priority Processing</li>
                        <li>24/7 Support</li>
                    </ul>
                    @auth
                        <a href="{{ route('trademark.type-selection') }}" class="pricing-btn">Apply Now</a>
                    @else
                        <a href="{{ route('register') }}" class="pricing-btn">Get Started</a>
                    @endauth
                </div>

                <div class="pricing-card">
                    <h4>Enterprise</h4>
                    <p class="pricing-description">For large-scale operations</p>
                    <div class="pricing-amount">Custom</div>
                    <p class="pricing-period">Contact for pricing</p>
                    <ul class="pricing-features">
                        <li>Unlimited Classes</li>
                        <li>International Filing</li>
                        <li>Dedicated Manager</li>
                        <li>Custom Solutions</li>
                        <li>Premium Support</li>
                    </ul>
                    <a href="{{ route('contact') }}" class="pricing-btn" style="background: var(--slate);">Contact Us</a>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ TESTIMONIALS SECTION ============ -->
    @php($displayedCustomerReviews = $customerReviews ?? collect())
    @if ($displayedCustomerReviews->isNotEmpty())
    <section class="testimonials-section" id="testimonials">
        <div class="container">
            <div class="section-header">
                <h2>Loved by Thousands</h2>
                <p>See what our customers say about their experience</p>
            </div>

            <div class="testimonials-carousel" data-testimonials-carousel aria-roledescription="carousel" aria-label="Customer reviews">
                <div class="testimonials-viewport">
                    <div class="testimonials-track" data-testimonials-track>
                        @foreach ($displayedCustomerReviews as $review)
                            <article class="testimonial-card" data-testimonial-slide>
                                <div class="stars" aria-label="{{ $review->rating }} out of 5 stars">
                                    <span aria-hidden="true">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                                </div>
                                <p class="testimonial-text">“{{ $review->review }}”</p>
                                <div class="testimonial-author">
                                    <div class="author-avatar {{ $review->logo_path ? 'has-image' : '' }}" aria-hidden="true">
                                        @if ($review->logo_path)
                                            <img src="{{ route('storage.public.view', ['path' => $review->logo_path]) }}" alt="" loading="lazy">
                                        @else
                                            {{ $review->initials }}
                                        @endif
                                    </div>
                                    <div class="author-info">
                                        <div class="author-name">{{ $review->customer_name }}</div>
                                        <div class="author-title">{{ $review->customer_title }}</div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
                @if ($displayedCustomerReviews->count() > 1)
                    <div class="testimonials-controls">
                        <button type="button" class="testimonial-arrow" data-testimonials-prev aria-label="Previous reviews">
                            <i class="bi bi-arrow-left" aria-hidden="true"></i>
                        </button>
                        <div class="testimonials-dots" data-testimonials-dots aria-label="Choose a review page"></div>
                        <button type="button" class="testimonial-arrow" data-testimonials-next aria-label="Next reviews">
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </section>
    @endif

    <!-- ============ CTA SECTION ============ -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to Protect Your Brand?</h2>
                <p>Join thousands of satisfied customers. Start your trademark registration today and get 24/7 expert
                    support.</p>
                <div class="cta-buttons">
                    @auth
                        <a href="{{ route('trademark.type-selection') }}" class="cta-btn cta-btn-primary">
                            Start Registration Now
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="cta-btn cta-btn-primary">
                            Sign Up Free
                        </a>
                    @endauth
                    <a href="{{ route('contact') }}" class="cta-btn cta-btn-secondary">
                        Schedule a Call
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ FOOTER ============ -->
    @include('partials.site-footer')

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const tmSearchState = {
            keyword: '',
            results: [],
            filtered: [],
            searchCompleted: false,
            searchSucceeded: false,
            searchLoading: false,
            analysisLoading: false,
            currentAnalysis: null,
            savedSearches: JSON.parse(localStorage.getItem('legalbruzSavedTrademarkSearches') || '[]'),
            filters: {
                statuses: new Set(['All Status']),
                classes: new Set(['All Classes']),
                types: new Set(['All Types']),
                date: 'anytime',
                dateFrom: '',
                dateTo: '',
                classQuery: '',
            },
            sort: 'relevance',
            page: 1,
            rows: 10,
            classExpanded: false,
        };

        const tmEls = {};

        function initTrademarkSearch() {
            [
                'tm-entry-form', 'tm-search-keyword', 'tm-search-submit', 'tm-search-reset', 'tm-search-note',
                'tm-results-shell', 'tm-results-keyword', 'tm-clear-inline', 'tm-strip-submit',
                'tm-ai-probability', 'tm-probability-modal',
                'tm-probability-rerun', 'tm-probability-download', 'tm-probability-keyword', 'tm-probability-loading',
                'tm-probability-error', 'tm-probability-content', 'tm-registration-chance',
                'tm-conflict-risk', 'tm-risk-level', 'tm-count-word', 'tm-count-device',
                'tm-count-similar', 'tm-count-class', 'tm-probability-reasons',
                'tm-probability-warnings', 'tm-warnings-section', 'tm-probability-disclaimer',
                'tm-ai-summary', 'tm-probability-details', 'tm-probability-overview-card',
                'tm-analysis-quality',
                'tm-refine-panel', 'tm-refine-head', 'tm-sheet-grab', 'tm-filter-sheet-content',
                'tm-mobile-filter-trigger',
                'tm-mobile-filter-backdrop', 'tm-mobile-panel-close',
                'tm-save-search', 'tm-clear-filters', 'tm-status-options', 'tm-class-search',
                'tm-class-options', 'tm-class-show-more', 'tm-type-options', 'tm-date-from',
                'tm-date-to', 'tm-apply-filters', 'tm-sort-select', 'tm-results-title',
                'tm-results-summary', 'tm-stat-total', 'tm-stat-registered', 'tm-stat-classes',
                'tm-stat-updated', 'tm-loading', 'tm-empty-state', 'tm-card-list',
                'tm-rows-select', 'tm-prev-page', 'tm-next-page', 'tm-page-indicator',
            ].forEach(id => tmEls[id] = document.getElementById(id));

            tmEls['tm-entry-form']?.addEventListener('submit', event => {
                const keyword = tmEls['tm-search-keyword'].value.trim();

                if (!keyword) {
                    event.preventDefault();
                    setTrademarkNote('Please enter a brand or trademark keyword.', true);
                    tmEls['tm-search-keyword']?.focus();
                    return;
                }

                tmEls['tm-search-keyword'].value = keyword;
            });
            tmEls['tm-search-submit']?.addEventListener('click', () => handleTrademarkEntrySearch(tmEls['tm-search-keyword'].value));
            tmEls['tm-strip-submit']?.addEventListener('click', () => runTrademarkSearch(tmEls['tm-results-keyword'].value));
            tmEls['tm-ai-probability']?.addEventListener('click', openTrademarkProbability);
            tmEls['tm-probability-rerun']?.addEventListener('click', analyzeTrademarkProbability);
            tmEls['tm-probability-download']?.addEventListener('click', downloadTrademarkProbabilityReport);
            tmEls['tm-mobile-filter-trigger']?.addEventListener('click', openTrademarkFilterSheet);
            tmEls['tm-search-reset']?.addEventListener('click', clearTrademarkSearch);
            tmEls['tm-clear-inline']?.addEventListener('click', () => {
                tmEls['tm-results-keyword'].value = '';
                tmEls['tm-results-keyword'].focus();
            });
            tmEls['tm-save-search']?.addEventListener('click', saveTrademarkSearch);
            tmEls['tm-clear-filters']?.addEventListener('click', () => clearTrademarkFilters(true));
            tmEls['tm-apply-filters']?.addEventListener('click', () => {
                tmSearchState.page = 1;
                applyTrademarkFilters();
                closeTrademarkFilterSheet();
            });
            tmEls['tm-sort-select']?.addEventListener('change', event => {
                tmSearchState.sort = event.target.value;
                tmSearchState.page = 1;
                renderTrademarkResults();
            });
            tmEls['tm-rows-select']?.addEventListener('change', event => {
                tmSearchState.rows = Number(event.target.value);
                tmSearchState.page = 1;
                renderTrademarkResults();
            });
            tmEls['tm-prev-page']?.addEventListener('click', () => changeTrademarkPage(-1));
            tmEls['tm-next-page']?.addEventListener('click', () => changeTrademarkPage(1));
            tmEls['tm-class-search']?.addEventListener('input', event => {
                tmSearchState.filters.classQuery = event.target.value.trim().toLowerCase();
                renderClassFilters();
            });
            tmEls['tm-class-show-more']?.addEventListener('click', () => {
                tmSearchState.classExpanded = !tmSearchState.classExpanded;
                renderClassFilters();
            });
            tmEls['tm-date-from']?.addEventListener('change', event => {
                tmSearchState.filters.dateFrom = event.target.value;
                setTrademarkDateFilter('custom');
            });
            tmEls['tm-date-to']?.addEventListener('change', event => {
                tmSearchState.filters.dateTo = event.target.value;
                setTrademarkDateFilter('custom');
            });

            document.querySelectorAll('input[name="tm-date-filter"]').forEach(input => {
                input.addEventListener('change', event => setTrademarkDateFilter(event.target.value));
            });
            document.querySelectorAll('.tm-filter-title').forEach(button => {
                button.addEventListener('click', () => button.closest('.tm-filter-group')?.classList.toggle('is-collapsed'));
            });
            initTrademarkFilterSheet();
            [tmEls['tm-search-keyword'], tmEls['tm-results-keyword']].forEach(input => {
                input?.addEventListener('keydown', event => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        if (event.target.id === 'tm-search-keyword') {
                            handleTrademarkEntrySearch(event.target.value);
                        } else {
                            runTrademarkSearch(event.target.value);
                        }
                    }
                });
            });

            renderTrademarkResults();

            if (isDedicatedTrademarkSearchPage()) {
                const keyword = new URLSearchParams(window.location.search).get('keyword') || '';
                tmEls['tm-search-keyword'].value = keyword;
                tmEls['tm-results-keyword'].value = keyword;

                if (keyword.trim()) {
                    runTrademarkSearch(keyword);
                }
            }
        }

        function isDedicatedTrademarkSearchPage() {
            return document.body.classList.contains('trademark-search-page');
        }

        const tmFilterSheetMedia = window.matchMedia('(max-width: 1024px)');
        let tmFilterSheetDrag = null;

        function initTrademarkFilterSheet() {
            const panel = tmEls['tm-refine-panel'];
            const grab = tmEls['tm-sheet-grab'];
            if (!panel || !grab) return;

            [grab, tmEls['tm-refine-head']].filter(Boolean).forEach(surface => {
                surface.addEventListener('pointerdown', startTrademarkFilterSheetDrag);
                surface.addEventListener('pointermove', moveTrademarkFilterSheetDrag);
                surface.addEventListener('pointerup', finishTrademarkFilterSheetDrag);
                surface.addEventListener('pointercancel', finishTrademarkFilterSheetDrag);
                surface.addEventListener('click', event => {
                    if (event.target.closest('button') || tmFilterSheetDrag?.moved) return;
                    toggleTrademarkFilterSheet();
                });
            });
            grab.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    toggleTrademarkFilterSheet();
                }
            });
            tmEls['tm-mobile-filter-backdrop']?.addEventListener('click', closeTrademarkFilterSheet);
            tmEls['tm-mobile-panel-close']?.addEventListener('click', closeTrademarkFilterSheet);
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') closeTrademarkFilterSheet();
            });
            if (tmFilterSheetMedia.addEventListener) {
                tmFilterSheetMedia.addEventListener('change', syncTrademarkFilterSheetLayout);
            } else {
                tmFilterSheetMedia.addListener(syncTrademarkFilterSheetLayout);
            }
            syncTrademarkFilterSheetLayout();
        }

        function collapsedTrademarkFilterSheetOffset() {
            const panel = tmEls['tm-refine-panel'];
            if (!panel) return 0;
            const peekHeight = Number.parseFloat(getComputedStyle(panel).getPropertyValue('--tm-filter-sheet-peek')) || 112;
            return Math.max(0, panel.getBoundingClientRect().height - peekHeight);
        }

        function startTrademarkFilterSheetDrag(event) {
            if (!tmFilterSheetMedia.matches || event.button > 0 || event.target.closest('button')) return;
            const panel = tmEls['tm-refine-panel'];
            const collapsedOffset = collapsedTrademarkFilterSheetOffset();
            tmFilterSheetDrag = {
                pointerId: event.pointerId,
                startY: event.clientY,
                startOffset: tmEls['tm-results-shell'].classList.contains('is-mobile-filters-open') ? 0 : collapsedOffset,
                collapsedOffset,
                currentOffset: 0,
                moved: false,
            };
            tmFilterSheetDrag.currentOffset = tmFilterSheetDrag.startOffset;
            panel.classList.add('is-dragging');
            event.currentTarget.setPointerCapture(event.pointerId);
        }

        function moveTrademarkFilterSheetDrag(event) {
            if (!tmFilterSheetDrag || event.pointerId !== tmFilterSheetDrag.pointerId) return;
            const delta = event.clientY - tmFilterSheetDrag.startY;
            const offset = Math.min(tmFilterSheetDrag.collapsedOffset, Math.max(0, tmFilterSheetDrag.startOffset + delta));
            tmFilterSheetDrag.currentOffset = offset;
            tmFilterSheetDrag.moved ||= Math.abs(delta) > 6;
            tmEls['tm-refine-panel'].style.transform = `translate3d(0, ${offset}px, 0)`;
        }

        function finishTrademarkFilterSheetDrag(event) {
            if (!tmFilterSheetDrag || event.pointerId !== tmFilterSheetDrag.pointerId) return;
            const drag = tmFilterSheetDrag;
            const delta = event.clientY - drag.startY;
            const shouldOpen = delta < -55 || (delta <= 55 && drag.currentOffset < drag.collapsedOffset * 0.52);
            tmEls['tm-refine-panel'].classList.remove('is-dragging');
            tmEls['tm-refine-panel'].style.removeProperty('transform');
            tmFilterSheetDrag = { ...drag, moved: drag.moved };
            shouldOpen ? openTrademarkFilterSheet() : closeTrademarkFilterSheet();
            window.setTimeout(() => { tmFilterSheetDrag = null; }, 0);
        }

        function toggleTrademarkFilterSheet() {
            if (!tmFilterSheetMedia.matches) return;
            tmEls['tm-results-shell'].classList.contains('is-mobile-filters-open')
                ? closeTrademarkFilterSheet()
                : openTrademarkFilterSheet();
        }

        function openTrademarkFilterSheet() {
            if (!tmFilterSheetMedia.matches || !tmEls['tm-results-shell']) return;
            tmEls['tm-results-shell'].classList.add('is-mobile-filters-open');
            document.body.classList.add('tm-mobile-filters-open');
            tmEls['tm-sheet-grab']?.setAttribute('aria-expanded', 'true');
            const hint = tmEls['tm-sheet-grab']?.querySelector('small');
            if (hint) hint.textContent = 'Swipe down to close';
            if (tmEls['tm-filter-sheet-content']) tmEls['tm-filter-sheet-content'].inert = false;
        }

        function closeTrademarkFilterSheet() {
            if (!tmEls['tm-results-shell']) return;
            tmEls['tm-results-shell'].classList.remove('is-mobile-filters-open');
            document.body.classList.remove('tm-mobile-filters-open');
            tmEls['tm-sheet-grab']?.setAttribute('aria-expanded', 'false');
            const hint = tmEls['tm-sheet-grab']?.querySelector('small');
            if (hint) hint.textContent = 'Swipe up for filters';
            if (tmEls['tm-filter-sheet-content']) tmEls['tm-filter-sheet-content'].inert = tmFilterSheetMedia.matches;
        }

        function syncTrademarkFilterSheetLayout() {
            const panel = tmEls['tm-refine-panel'];
            if (!panel) return;
            panel.style.removeProperty('transform');
            if (tmFilterSheetMedia.matches) {
                closeTrademarkFilterSheet();
            } else {
                tmEls['tm-results-shell']?.classList.remove('is-mobile-filters-open');
                document.body.classList.remove('tm-mobile-filters-open');
                if (tmEls['tm-filter-sheet-content']) tmEls['tm-filter-sheet-content'].inert = false;
            }
        }

        function handleTrademarkEntrySearch(rawKeyword) {
            const keyword = (rawKeyword || '').trim();

            if (!keyword) {
                setTrademarkNote('Please enter a brand or trademark keyword.', true);
                tmEls['tm-search-keyword']?.focus();
                return;
            }

            if (isDedicatedTrademarkSearchPage()) {
                runTrademarkSearch(keyword);
                return;
            }

            window.location.href = `{{ route('trademark.search-page', [], false) }}?keyword=${encodeURIComponent(keyword)}`;
        }

        async function runTrademarkSearch(rawKeyword) {
            const keyword = (rawKeyword || '').trim();

            if (!keyword) {
                setTrademarkNote('Please enter a brand or trademark keyword.', true);
                tmEls['tm-search-keyword']?.focus();
                return;
            }

            if (!isDedicatedTrademarkSearchPage()) {
                window.location.href = `{{ route('trademark.search-page', [], false) }}?keyword=${encodeURIComponent(keyword)}`;
                return;
            }

            tmSearchState.keyword = keyword;
            tmSearchState.results = [];
            tmSearchState.filtered = [];
            tmSearchState.searchCompleted = false;
            tmSearchState.searchSucceeded = false;
            tmSearchState.currentAnalysis = null;
            tmSearchState.page = 1;
            tmSearchState.classExpanded = false;
            updateAiProbabilityButton();
            tmEls['tm-search-keyword'].value = keyword;
            tmEls['tm-results-keyword'].value = keyword;
            tmEls['tm-results-shell'].hidden = false;
            tmEls['tm-results-shell'].scrollIntoView({ behavior: 'smooth', block: 'start' });
            setTrademarkLoading(true);
            setTrademarkNote('Searching trademark records...', false);

            try {
                const response = await fetch(`/scrape-trademark?keyword=${encodeURIComponent(keyword)}`, {
                    headers: { 'Accept': 'application/json' },
                });

                if (!response.ok) {
                    throw new Error('Search request failed.');
                }

                const payload = await response.json();
                if (payload?.success !== true || !Array.isArray(payload.data)) {
                    throw new Error('Search response was invalid.');
                }
                tmSearchState.results = (payload.data || []).map((item, index) => normalizeTrademarkRecord(item, index));
                tmSearchState.searchCompleted = true;
                tmSearchState.searchSucceeded = true;
                clearTrademarkFilters(false);
                renderTrademarkDashboard();
                setTrademarkNote(
                    tmSearchState.results.length
                        ? `Loaded ${tmSearchState.results.length} trademark result${tmSearchState.results.length === 1 ? '' : 's'}.`
                        : (payload.message || 'No trademark records were found for this keyword.'),
                    false
                );
            } catch (error) {
                tmSearchState.results = [];
                tmSearchState.filtered = [];
                tmSearchState.searchCompleted = true;
                tmSearchState.searchSucceeded = false;
                renderTrademarkDashboard();
                setTrademarkNote('Unable to fetch trademark data right now. Please try again.', true);
            } finally {
                setTrademarkLoading(false);
            }
        }

        function normalizeTrademarkRecord(item, index) {
            const status = normalizeTitle(item.status || 'Unknown');
            const type = normalizeTrademarkType(item.type || 'Wordmark');
            const date = parseTrademarkDate(item.application_date);

            return {
                id: String(item.application_id || index + 1),
                applicationId: item.application_id ? String(item.application_id) : '',
                name: item.trademark_name || 'Untitled Trademark',
                proprietor: item.proprietor || 'Not available',
                status,
                class: String(item.class || 'Unclassified'),
                type,
                description: item.description || 'No description available.',
                dateLabel: item.application_date || '-',
                date,
                imageUrl: item.image_url || '',
                sourceUrl: item.source_url || '',
                sourceType: item.source_type || 'third_party',
                index,
            };
        }

        function normalizeTitle(value) {
            return String(value).toLowerCase().replace(/(^|\s|_|-)\S/g, match => match.toUpperCase()).replace(/[_-]/g, ' ').trim();
        }

        function normalizeTrademarkType(value) {
            const text = normalizeTitle(value);
            if (text === 'Word') return 'Wordmark';
            if (text === 'Logo' || text === 'Label') return 'Device';
            return text || 'Wordmark';
        }

        function parseTrademarkDate(value) {
            if (!value) return null;
            const parsed = new Date(String(value).replace(/(\d{1,2})\s+([A-Za-z]{3})\s+(\d{4})/, '$2 $1, $3'));
            return Number.isNaN(parsed.getTime()) ? null : parsed;
        }

        function setTrademarkLoading(isLoading) {
            tmSearchState.searchLoading = isLoading;
            tmEls['tm-loading'].hidden = !isLoading;
            tmEls['tm-search-submit'].disabled = isLoading;
            tmEls['tm-strip-submit'].disabled = isLoading;
            updateAiProbabilityButton();
        }

        function setTrademarkNote(message, isError) {
            tmEls['tm-search-note'].textContent = message;
            tmEls['tm-search-note'].classList.toggle('is-error', Boolean(isError));
        }

        function renderTrademarkDashboard() {
            renderTrademarkFilters();
            applyTrademarkFilters();
            updateSaveSearchButton();
            updateAiProbabilityButton();
        }

        let tmProbabilityModal = null;
        let tmProbabilityDoughnutChart = null;
        let tmProbabilityFactorsChart = null;

        function updateAiProbabilityButton() {
            if (!tmEls['tm-ai-probability']) return;
            const enabled = tmSearchState.searchCompleted
                && tmSearchState.searchSucceeded
                && tmSearchState.keyword.trim().length >= 2
                && !tmSearchState.searchLoading
                && !tmSearchState.analysisLoading;
            tmEls['tm-ai-probability'].disabled = !enabled;
        }

        function openTrademarkProbability() {
            if (!tmSearchState.searchCompleted || !tmSearchState.searchSucceeded || tmSearchState.keyword.trim().length < 2) return;
            tmProbabilityModal ??= new bootstrap.Modal(tmEls['tm-probability-modal']);
            tmEls['tm-probability-keyword'].textContent = tmSearchState.keyword;
            tmProbabilityModal.show();
            analyzeTrademarkProbability();
        }

        async function analyzeTrademarkProbability() {
            if (!tmSearchState.searchCompleted || !tmSearchState.searchSucceeded || tmSearchState.keyword.trim().length < 2) {
                showTrademarkProbabilityError('Complete a valid trademark search before running the analysis.');
                return;
            }

            setTrademarkProbabilityLoading(true);
            const records = tmSearchState.results.slice(0, 100).map(item => ({
                application_id: item.applicationId || null,
                trademark_name: item.name,
                status: item.status || null,
                class: item.class === 'Unclassified' ? null : item.class,
                type: item.type || null,
                proprietor: item.proprietor === 'Not available' ? null : item.proprietor,
                description: item.description === 'No description available.' ? null : item.description,
                application_date: item.dateLabel === '-' ? null : item.dateLabel,
                source_type: item.sourceType,
            }));

            try {
                const response = await fetch(`{{ route('trademark.ai-probability', [], false) }}`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        keyword: tmSearchState.keyword,
                        source_type: 'third_party',
                        data: records,
                    }),
                });
                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(trademarkProbabilityErrorMessage(response.status, payload));
                }
                renderTrademarkProbability(payload.analysis);
            } catch (error) {
                showTrademarkProbabilityError(error.message || 'Unable to analyze trademark data right now.');
            } finally {
                setTrademarkProbabilityLoading(false);
            }
        }

        function trademarkProbabilityErrorMessage(status, payload) {
            if (status === 422) {
                const messages = Object.values(payload.errors || {}).flat();
                return messages[0] || 'Please check the trademark data and try again.';
            }
            if (status === 419) return 'Your session expired. Refresh the page and try again.';
            if (status === 429) return 'Too many analyses were requested. Please wait a minute and try again.';
            if (status >= 500) return 'The analysis service is temporarily unavailable. Please try again.';
            return payload.message || 'Unable to analyze trademark data right now.';
        }

        function setTrademarkProbabilityLoading(isLoading) {
            tmSearchState.analysisLoading = isLoading;
            tmEls['tm-probability-loading'].hidden = !isLoading;
            tmEls['tm-probability-error'].hidden = true;
            tmEls['tm-probability-content'].hidden = isLoading;
            tmEls['tm-ai-probability'].disabled = isLoading;
            tmEls['tm-probability-rerun'].disabled = isLoading;
            if (tmEls['tm-probability-download']) tmEls['tm-probability-download'].disabled = isLoading || !tmSearchState.currentAnalysis;
            tmEls['tm-ai-probability'].querySelector('span').textContent = isLoading ? 'Analyzing...' : 'AI Probability';
            if (!isLoading) updateAiProbabilityButton();
        }

        function showTrademarkProbabilityError(message) {
            tmSearchState.currentAnalysis = null;
            tmEls['tm-probability-content'].hidden = true;
            tmEls['tm-probability-error'].textContent = message;
            tmEls['tm-probability-error'].hidden = false;
            if (tmEls['tm-probability-download']) tmEls['tm-probability-download'].disabled = true;
        }

        function renderTrademarkProbability(analysis) {
            const insights = analysis.ai_insights;
            tmSearchState.currentAnalysis = analysis;
            tmEls['tm-probability-error'].hidden = true;
            tmEls['tm-probability-content'].hidden = false;
            tmEls['tm-probability-keyword'].textContent = analysis.keyword;
            tmEls['tm-registration-chance'].textContent = `${analysis.registration_probability}%`;
            tmEls['tm-conflict-risk'].textContent = `${analysis.conflict_risk}%`;
            tmEls['tm-risk-level'].textContent = analysis.risk_level;
            tmEls['tm-risk-level'].className = `is-${analysis.risk_level.toLowerCase().replace(/\s+/g, '-')}`;
            tmEls['tm-analysis-quality'].textContent = analysis.analysis_quality.replace(/^./, character => character.toUpperCase());
            tmEls['tm-count-word'].textContent = analysis.exact_active_word_marks;
            tmEls['tm-count-device'].textContent = analysis.exact_active_device_marks;
            tmEls['tm-count-similar'].textContent = analysis.similar_active_marks;
            tmEls['tm-count-class'].textContent = analysis.unique_active_classes;
            tmEls['tm-ai-summary'].textContent = insights.summary;
            renderTrademarkInsightReasons(insights.reasons);
            renderTrademarkInsightWarnings(insights.warnings);
            tmEls['tm-probability-disclaimer'].textContent = analysis.disclaimer;
            renderTrademarkProbabilityCharts(analysis);
            if (tmEls['tm-probability-download']) tmEls['tm-probability-download'].disabled = false;
        }

        function renderTrademarkInsightReasons(reasons) {
            const list = tmEls['tm-probability-reasons'];
            list.replaceChildren();

            reasons.forEach(reason => {
                const item = document.createElement('li');
                const heading = document.createElement('div');
                const title = document.createElement('strong');
                const badge = document.createElement('span');
                const detail = document.createElement('p');
                title.textContent = reason.title;
                badge.textContent = { positive: 'Positive', negative: 'Risk', neutral: 'Info' }[reason.impact] || 'Info';
                badge.className = `tm-impact-badge is-${reason.impact}`;
                detail.textContent = reason.detail;
                heading.append(title, badge);
                item.append(heading, detail);
                list.append(item);
            });
        }

        function renderTrademarkInsightWarnings(warnings) {
            const list = tmEls['tm-probability-warnings'];
            const hasWarnings = warnings.length > 0;
            list.replaceChildren();
            tmEls['tm-warnings-section'].hidden = !hasWarnings;
            tmEls['tm-probability-details'].classList.toggle('has-no-warnings', !hasWarnings);

            warnings.forEach(warning => {
                const item = document.createElement('li');
                const title = document.createElement('strong');
                const detail = document.createElement('p');
                const action = document.createElement('p');
                title.textContent = warning.title;
                detail.textContent = warning.detail;
                action.textContent = `Recommended action: ${warning.action}`;
                action.className = 'tm-warning-action';
                item.append(title, detail, action);
                list.append(item);
            });
        }

        function renderTrademarkProbabilityCharts(analysis) {
            tmProbabilityDoughnutChart?.destroy();
            tmProbabilityFactorsChart?.destroy();
            tmProbabilityDoughnutChart = null;
            tmProbabilityFactorsChart = null;
            const ChartConstructor = window.Chart;
            if (!ChartConstructor) {
                showTrademarkProbabilityError('Charts could not be loaded. Please refresh the page and try again.');
                return;
            }
            tmProbabilityDoughnutChart = new ChartConstructor(document.getElementById('tm-probability-doughnut'), {
                type: 'doughnut',
                data: { labels: ['Registration Chance', 'Conflict Risk'], datasets: [{ data: [analysis.registration_probability, analysis.conflict_risk], backgroundColor: ['#0aa58f', '#ef5b62'], borderWidth: 0 }] },
                options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom' } } },
            });
            const factors = analysis.factors.filter(factor => factor.score !== null);
            tmProbabilityFactorsChart = new ChartConstructor(document.getElementById('tm-probability-factors'), {
                type: 'bar',
                data: { labels: factors.map(factor => factor.label), datasets: [{ label: 'Factor score', data: factors.map(factor => factor.score), backgroundColor: '#335f8a', borderRadius: 7 }] },
                options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, scales: { x: { beginAtZero: true, max: 100 } }, plugins: { legend: { display: false } } },
            });
        }

        async function downloadTrademarkProbabilityReport() {
            const analysis = tmSearchState.currentAnalysis;

            if (!analysis) {
                showTrademarkProbabilityError('Run the trademark probability analysis before downloading the report.');
                return;
            }

            const button = tmEls['tm-probability-download'];
            const originalLabel = button?.innerHTML;

            try {
                if (button) {
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Preparing PDF...';
                }

                const response = await fetch(`{{ route('trademark.probability-report', [], false) }}`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/pdf',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        keyword: analysis.keyword || tmSearchState.keyword,
                        analysis,
                        records: tmSearchState.results.slice(0, 25).map(item => ({
                            application_id: item.applicationId || item.id || null,
                            trademark_name: item.name,
                            status: item.status,
                            class: item.class === 'Unclassified' ? null : item.class,
                            type: item.type,
                            proprietor: item.proprietor === 'Not available' ? null : item.proprietor,
                        })),
                    }),
                });

                if (!response.ok) {
                    throw new Error('Unable to generate the PDF report right now.');
                }

                const blob = await response.blob();
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = `${slugifyFilename(analysis.keyword || tmSearchState.keyword || 'trademark')}-probability-report.pdf`;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);
            } catch (error) {
                showTrademarkProbabilityError(error.message || 'Unable to generate the PDF report right now.');
            } finally {
                if (button) {
                    button.innerHTML = originalLabel || '<i class="bi bi-download"></i> Download Report';
                    button.disabled = !tmSearchState.currentAnalysis;
                }
            }
        }

        function buildTrademarkProbabilityReportHtml(analysis) {
            const insights = analysis.ai_insights || {};
            const reasons = Array.isArray(insights.reasons) ? insights.reasons : [];
            const warnings = Array.isArray(insights.warnings) ? insights.warnings : [];
            const factors = Array.isArray(analysis.factors) ? analysis.factors : [];
            const records = tmSearchState.results.slice(0, 25);
            const generatedAt = new Intl.DateTimeFormat('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                timeZone: 'Europe/London',
                timeZoneName: 'short',
            }).format(new Date());
            const overviewChart = document.getElementById('tm-probability-doughnut')?.toDataURL('image/png') || '';
            const factorsChart = document.getElementById('tm-probability-factors')?.toDataURL('image/png') || '';

            return `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${escapeHtml(analysis.keyword)} - Trademark Probability Report</title>
<style>
body{margin:0;background:#f4f8fb;color:#1d3557;font-family:"DM Sans",Arial,sans-serif;line-height:1.55}
.page{max-width:1040px;margin:0 auto;padding:34px 24px 48px}
.hero,.card{background:#fff;border:1px solid #dfe8f1;border-radius:18px;box-shadow:0 16px 42px rgba(29,53,87,.08)}
.hero{padding:34px;margin-bottom:22px}
.kicker{color:#6754e8;font-weight:900;text-transform:uppercase;letter-spacing:.05em}
h1{margin:8px 0 6px;font-size:34px;line-height:1.1}
h2{margin:0 0 16px;font-size:20px}.muted{color:#687792}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:20px 0}
.metric{padding:20px;border:1px solid #e3ebf3;border-radius:14px;background:#fbfdff}.metric span{display:block;color:#71809a;font-weight:800}.metric strong{display:block;margin-top:8px;font-size:34px}.ok{color:#078d80}.risk{color:#dc454e}
.card{padding:24px;margin-top:18px}.summary{background:#f8f7ff;border-color:#dedafe}.charts{display:grid;grid-template-columns:1fr 1fr;gap:18px}.chart img{max-width:100%;height:auto}
ul{padding-left:22px}li{margin:9px 0}.badge{display:inline-block;margin-left:8px;padding:3px 8px;border-radius:999px;background:#eef2f6;color:#526076;font-size:11px;font-weight:900;text-transform:uppercase}
table{width:100%;border-collapse:collapse;font-size:13px}th,td{padding:10px;border-bottom:1px solid #e5edf4;text-align:left;vertical-align:top}th{color:#526076;background:#f8fafc}
.actions{margin-top:22px}.print{display:inline-block;padding:11px 16px;border-radius:10px;background:#078d80;color:#fff;text-decoration:none;font-weight:900}
@media print{body{background:#fff}.page{max-width:none;padding:0}.actions{display:none}.hero,.card{box-shadow:none;break-inside:avoid}}@media(max-width:760px){.grid,.charts{grid-template-columns:1fr}h1{font-size:28px}}
</style>
</head>
<body>
<main class="page">
<section class="hero">
<div class="kicker">Legal Bruz Ltd. · Trademark Registration Probability</div>
<h1>${escapeHtml(analysis.keyword)}</h1>
<p class="muted">Generated on ${escapeHtml(generatedAt)} from ${tmSearchState.results.length} searched trademark record${tmSearchState.results.length === 1 ? '' : 's'}.</p>
<div class="grid">
<div class="metric"><span>Registration Chance</span><strong class="ok">${Number(analysis.registration_probability) || 0}%</strong></div>
<div class="metric"><span>Conflict Risk</span><strong class="risk">${Number(analysis.conflict_risk) || 0}%</strong></div>
<div class="metric"><span>Risk Level</span><strong>${escapeHtml(analysis.risk_level || '—')}</strong></div>
</div>
<div class="actions"><a class="print" href="#" onclick="window.print();return false;">Print / Save as PDF</a></div>
</section>
<section class="card summary"><h2>AI Analysis Summary</h2><p>${escapeHtml(insights.summary || '')}</p></section>
<section class="card charts"><div class="chart"><h2>Probability Overview</h2>${overviewChart ? `<img src="${overviewChart}" alt="Probability overview chart">` : ''}</div><div class="chart"><h2>Risk Factors</h2>${factorsChart ? `<img src="${factorsChart}" alt="Risk factors chart">` : factorRows(factors)}</div></section>
<section class="card"><h2>Conflict Counts</h2><div class="grid"><div class="metric"><span>Exact active Word marks</span><strong>${Number(analysis.exact_active_word_marks) || 0}</strong></div><div class="metric"><span>Exact active Device marks</span><strong>${Number(analysis.exact_active_device_marks) || 0}</strong></div><div class="metric"><span>Similar active marks</span><strong>${Number(analysis.similar_active_marks) || 0}</strong></div></div></section>
<section class="card"><h2>Reasons</h2>${listRows(reasons, reason => `<strong>${escapeHtml(reason.title || 'Reason')}</strong><span class="badge">${escapeHtml(reason.impact || 'info')}</span><p>${escapeHtml(reason.detail || '')}</p>`)}</section>
${warnings.length ? `<section class="card"><h2>Warnings</h2>${listRows(warnings, warning => `<strong>${escapeHtml(warning.title || 'Warning')}</strong><p>${escapeHtml(warning.detail || '')}</p><p><strong>Recommended action:</strong> ${escapeHtml(warning.action || '')}</p>`)}</section>` : ''}
<section class="card"><h2>Top Search Records Used</h2>${recordTable(records)}</section>
<section class="card"><h2>Disclaimer</h2><p>${escapeHtml(analysis.disclaimer || 'This report is an informational search-data estimate and is not legal advice or a guarantee of registry outcome.')}</p></section>
</main>
</body>
</html>`;
        }

        function factorRows(factors) {
            if (!factors.length) return '<p class="muted">No factor data available.</p>';
            return `<ul>${factors.map(factor => `<li><strong>${escapeHtml(factor.label || '')}:</strong> ${factor.score === null ? 'Not applicable' : `${Number(factor.score)}%`}</li>`).join('')}</ul>`;
        }

        function listRows(items, renderer) {
            if (!items.length) return '<p class="muted">No items reported.</p>';
            return `<ul>${items.map(item => `<li>${renderer(item)}</li>`).join('')}</ul>`;
        }

        function recordTable(records) {
            if (!records.length) return '<p class="muted">No trademark records were available.</p>';
            return `<table><thead><tr><th>Application ID</th><th>Trademark</th><th>Status</th><th>Class</th><th>Type</th><th>Proprietor</th></tr></thead><tbody>${records.map(record => `<tr><td>${escapeHtml(record.applicationId || record.id || '')}</td><td>${escapeHtml(record.name || '')}</td><td>${escapeHtml(record.status || '')}</td><td>${escapeHtml(record.class || '')}</td><td>${escapeHtml(record.type || '')}</td><td>${escapeHtml(record.proprietor || '')}</td></tr>`).join('')}</tbody></table>`;
        }

        function slugifyFilename(value) {
            return String(value || 'trademark').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60) || 'trademark';
        }

        function renderTrademarkFilters() {
            renderOptionFilters('status', tmEls['tm-status-options'], 'All Status', getTrademarkCounts('status'));
            renderClassFilters();
            renderOptionFilters('type', tmEls['tm-type-options'], 'All Types', getTrademarkCounts('type'));
        }

        function renderClassFilters() {
            const counts = getTrademarkCounts('class');
            const query = tmSearchState.filters.classQuery;
            const options = Object.entries(counts)
                .filter(([value]) => value.toLowerCase().includes(query))
                .sort((a, b) => Number(a[0]) - Number(b[0]) || a[0].localeCompare(b[0]));
            const visibleOptions = tmSearchState.classExpanded ? options : options.slice(0, 5);

            tmEls['tm-class-options'].innerHTML = renderFilterRow('class', 'All Classes', tmSearchState.results.length, tmSearchState.filters.classes.has('All Classes'));
            tmEls['tm-class-options'].insertAdjacentHTML('beforeend', visibleOptions.map(([value, count]) => renderFilterRow('class', value, count, tmSearchState.filters.classes.has(value))).join(''));
            tmEls['tm-class-show-more'].hidden = options.length <= 5;
            tmEls['tm-class-show-more'].textContent = tmSearchState.classExpanded ? 'Show less' : 'Show more';
            bindFilterRows(tmEls['tm-class-options']);
        }

        function renderOptionFilters(filterName, container, allLabel, counts) {
            const stateKey = filterName === 'status' ? 'statuses' : 'types';
            const sorted = Object.entries(counts).sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]));
            container.innerHTML = renderFilterRow(filterName, allLabel, tmSearchState.results.length, tmSearchState.filters[stateKey].has(allLabel));
            container.insertAdjacentHTML('beforeend', sorted.map(([value, count]) => renderFilterRow(filterName, value, count, tmSearchState.filters[stateKey].has(value))).join(''));
            bindFilterRows(container);
        }

        function renderFilterRow(filterName, value, count, checked) {
            return `<label class="tm-check-row"><input type="checkbox" data-filter="${filterName}" value="${escapeHtml(value)}" ${checked ? 'checked' : ''}><span>${escapeHtml(value)}</span><small>${count}</small></label>`;
        }

        function bindFilterRows(container) {
            container.querySelectorAll('input[type="checkbox"]').forEach(input => {
                input.addEventListener('change', event => updateTrademarkSetFilter(event.target));
            });
        }

        function updateTrademarkSetFilter(input) {
            const map = {
                status: ['statuses', 'All Status'],
                class: ['classes', 'All Classes'],
                type: ['types', 'All Types'],
            };
            const [stateKey, allLabel] = map[input.dataset.filter];
            const filterSet = tmSearchState.filters[stateKey];

            if (input.value === allLabel) {
                filterSet.clear();
                filterSet.add(allLabel);
            } else {
                filterSet.delete(allLabel);
                input.checked ? filterSet.add(input.value) : filterSet.delete(input.value);
                if (filterSet.size === 0) filterSet.add(allLabel);
            }

            renderTrademarkFilters();
        }

        function getTrademarkCounts(field) {
            return tmSearchState.results.reduce((counts, item) => {
                const value = item[field] || 'Unknown';
                counts[value] = (counts[value] || 0) + 1;
                return counts;
            }, {});
        }

        function setTrademarkDateFilter(value) {
            tmSearchState.filters.date = value;
            document.querySelectorAll('input[name="tm-date-filter"]').forEach(input => input.checked = input.value === value);
        }

        function clearTrademarkFilters(shouldRender = true) {
            tmSearchState.filters.statuses = new Set(['All Status']);
            tmSearchState.filters.classes = new Set(['All Classes']);
            tmSearchState.filters.types = new Set(['All Types']);
            tmSearchState.filters.date = 'anytime';
            tmSearchState.filters.dateFrom = '';
            tmSearchState.filters.dateTo = '';
            tmSearchState.filters.classQuery = '';
            tmSearchState.sort = 'relevance';
            tmSearchState.page = 1;
            tmSearchState.classExpanded = false;
            if (tmEls['tm-class-search']) tmEls['tm-class-search'].value = '';
            if (tmEls['tm-date-from']) tmEls['tm-date-from'].value = '';
            if (tmEls['tm-date-to']) tmEls['tm-date-to'].value = '';
            if (tmEls['tm-sort-select']) tmEls['tm-sort-select'].value = 'relevance';
            setTrademarkDateFilter('anytime');
            if (shouldRender) renderTrademarkDashboard();
        }

        function applyTrademarkFilters() {
            const now = new Date();
            const fromDate = tmSearchState.filters.dateFrom ? new Date(tmSearchState.filters.dateFrom) : null;
            const toDate = tmSearchState.filters.dateTo ? new Date(tmSearchState.filters.dateTo) : null;

            tmSearchState.filtered = tmSearchState.results.filter(item => {
                if (!matchesSetFilter(item.status, tmSearchState.filters.statuses, 'All Status')) return false;
                if (!matchesSetFilter(item.class, tmSearchState.filters.classes, 'All Classes')) return false;
                if (!matchesSetFilter(item.type, tmSearchState.filters.types, 'All Types')) return false;
                if (tmSearchState.filters.date === 'anytime') return true;
                if (!item.date) return false;
                if (tmSearchState.filters.date === 'custom') {
                    if (fromDate && item.date < fromDate) return false;
                    if (toDate && item.date > toDate) return false;
                    return true;
                }
                const cutoff = new Date(now);
                if (tmSearchState.filters.date === '3m') cutoff.setMonth(cutoff.getMonth() - 3);
                if (tmSearchState.filters.date === '6m') cutoff.setMonth(cutoff.getMonth() - 6);
                if (tmSearchState.filters.date === '1y') cutoff.setFullYear(cutoff.getFullYear() - 1);
                return item.date >= cutoff;
            });

            renderTrademarkResults();
        }

        function matchesSetFilter(value, filterSet, allLabel) {
            return filterSet.has(allLabel) || filterSet.has(value);
        }

        function renderTrademarkResults() {
            if (!tmEls['tm-card-list']) return;
            const sorted = [...tmSearchState.filtered].sort(sortTrademarkRecords);
            const totalPages = Math.max(1, Math.ceil(sorted.length / tmSearchState.rows));
            tmSearchState.page = Math.min(tmSearchState.page, totalPages);
            const start = (tmSearchState.page - 1) * tmSearchState.rows;
            const pageItems = sorted.slice(start, start + tmSearchState.rows);

            tmEls['tm-results-title'].textContent = tmSearchState.keyword ? `Search Results for "${tmSearchState.keyword}"` : 'Search Results';
            tmEls['tm-results-summary'].textContent = sorted.length ? `Showing ${start + 1}-${start + pageItems.length} of ${sorted.length} results` : 'Showing 0 results';
            tmEls['tm-card-list'].innerHTML = pageItems.map((item, index) => renderTrademarkCard(item, index === 0 && tmSearchState.page === 1)).join('');
            tmEls['tm-empty-state'].hidden = sorted.length !== 0 || !tmSearchState.keyword;
            tmEls['tm-page-indicator'].textContent = String(tmSearchState.page);
            tmEls['tm-prev-page'].disabled = tmSearchState.page <= 1;
            tmEls['tm-next-page'].disabled = tmSearchState.page >= totalPages;
            renderTrademarkStats(sorted);
        }

        function sortTrademarkRecords(a, b) {
            if (tmSearchState.sort === 'newest') return dateValue(b.date) - dateValue(a.date);
            if (tmSearchState.sort === 'oldest') return dateValue(a.date) - dateValue(b.date);
            if (tmSearchState.sort === 'status') return a.status.localeCompare(b.status);
            if (tmSearchState.sort === 'class') return Number(a.class) - Number(b.class) || a.class.localeCompare(b.class);
            return a.index - b.index;
        }

        function dateValue(date) {
            return date ? date.getTime() : 0;
        }

        function renderTrademarkStats(items) {
            const registered = items.filter(item => item.status.toLowerCase() === 'registered').length;
            const classCount = new Set(items.map(item => item.class).filter(Boolean)).size;
            const lastDate = items.map(item => item.date).filter(Boolean).sort((a, b) => b - a)[0];
            tmEls['tm-stat-total'].textContent = items.length;
            tmEls['tm-stat-registered'].textContent = registered;
            tmEls['tm-stat-classes'].textContent = classCount;
            tmEls['tm-stat-updated'].textContent = lastDate ? formatTrademarkDate(lastDate) : '-';
        }

        function renderTrademarkCard(item, isBestMatch) {
            return `
                <article class="tm-result-card">
                    ${isBestMatch ? '<span class="tm-best-match">Best Match</span>' : ''}
                    <div class="tm-mark-preview">${item.imageUrl ? `<img src="${escapeHtml(item.imageUrl)}" alt="${escapeHtml(item.name)} trademark image" loading="lazy">` : `<div class="tm-mark-fallback">${escapeHtml(getInitials(item.name))}</div>`}</div>
                    <div class="tm-result-identity">
                        <h5>${escapeHtml(item.name)}</h5>
                        <dl><dt>Application ID</dt><dd>${escapeHtml(item.id)}</dd><dt>Proprietor</dt><dd>${escapeHtml(item.proprietor)}</dd></dl>
                    </div>
                    <div class="tm-result-meta">
                        <dl><dt>Status</dt><dd><span class="tm-status-pill ${statusClass(item.status)}">${escapeHtml(item.status)}</span></dd><dt>Class</dt><dd>${escapeHtml(item.class)}</dd><dt>Type</dt><dd>${escapeHtml(item.type)}</dd></dl>
                    </div>
                    <div class="tm-result-description">
                        <dl><dt>Application Date</dt><dd>${escapeHtml(item.dateLabel)}</dd><dt>Description</dt><dd>${escapeHtml(item.description)}</dd></dl>
                    </div>
                </article>
            `;
        }

        function statusClass(status) {
            const key = status.toLowerCase();
            if (key.includes('registered') || key.includes('accepted')) return 'is-registered';
            if (key.includes('objected') || key.includes('opposed')) return 'is-objected';
            if (key.includes('abandoned') || key.includes('refused') || key.includes('removed')) return 'is-abandoned';
            if (key.includes('withdrawn')) return 'is-withdrawn';
            return 'is-neutral';
        }

        function getInitials(name) {
            return String(name).split(/\s+/).filter(Boolean).slice(0, 2).map(word => word[0]).join('').toUpperCase() || 'TM';
        }

        function formatTrademarkDate(date) {
            return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        }

        function changeTrademarkPage(direction) {
            const totalPages = Math.max(1, Math.ceil(tmSearchState.filtered.length / tmSearchState.rows));
            tmSearchState.page = Math.min(Math.max(1, tmSearchState.page + direction), totalPages);
            renderTrademarkResults();
        }

        function saveTrademarkSearch() {
            if (!tmSearchState.keyword) {
                setTrademarkNote('Search a keyword before saving it.', true);
                return;
            }
            const saved = new Set(tmSearchState.savedSearches);
            const existed = saved.has(tmSearchState.keyword);
            existed ? saved.delete(tmSearchState.keyword) : saved.add(tmSearchState.keyword);
            tmSearchState.savedSearches = [...saved];
            localStorage.setItem('legalbruzSavedTrademarkSearches', JSON.stringify(tmSearchState.savedSearches));
            updateSaveSearchButton();
            setTrademarkNote(existed ? 'Search removed from saved searches.' : 'Search saved in this browser.', false);
        }

        function updateSaveSearchButton() {
            if (!tmEls['tm-save-search']) return;
            const isSaved = tmSearchState.savedSearches.includes(tmSearchState.keyword);
            tmEls['tm-save-search'].classList.toggle('is-saved', isSaved);
            tmEls['tm-save-search'].querySelector('span').textContent = isSaved ? 'Saved' : 'Save Search';
            tmEls['tm-save-search'].querySelector('i').className = isSaved ? 'bi bi-bookmark-fill' : 'bi bi-bookmark';
        }

        function clearTrademarkSearch() {
            tmSearchState.keyword = '';
            tmSearchState.results = [];
            tmSearchState.filtered = [];
            tmSearchState.searchCompleted = false;
            tmSearchState.searchSucceeded = false;
            if (tmEls['tm-search-keyword']) tmEls['tm-search-keyword'].value = '';
            if (tmEls['tm-results-keyword']) tmEls['tm-results-keyword'].value = '';
            if (tmEls['tm-results-shell']) tmEls['tm-results-shell'].hidden = true;
            clearTrademarkFilters(false);
            renderTrademarkResults();
            updateAiProbabilityButton();
            setTrademarkNote('Search results will open below with filters and sorting.', false);
        }

        function escapeHtml(value) {
            const element = document.createElement('div');
            element.textContent = value ?? '';
            return element.innerHTML;
        }

        // Responsive customer reviews carousel
        document.querySelectorAll('[data-testimonials-carousel]').forEach(carousel => {
            const track = carousel.querySelector('[data-testimonials-track]');
            const slides = [...carousel.querySelectorAll('[data-testimonial-slide]')];
            const previousButton = carousel.querySelector('[data-testimonials-prev]');
            const nextButton = carousel.querySelector('[data-testimonials-next]');
            const dotsContainer = carousel.querySelector('[data-testimonials-dots]');
            const controls = carousel.querySelector('.testimonials-controls');
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            let page = 0;
            let pageCount = 1;
            let timer = null;
            let touchStartX = null;

            const perView = () => window.innerWidth < 576 ? 1 : (window.innerWidth < 992 ? 2 : 3);

            const renderDots = () => {
                if (!dotsContainer) return;
                dotsContainer.innerHTML = '';
                for (let index = 0; index < pageCount; index += 1) {
                    const dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = `testimonial-dot${index === page ? ' is-active' : ''}`;
                    dot.setAttribute('aria-label', `Show review page ${index + 1}`);
                    dot.setAttribute('aria-current', index === page ? 'true' : 'false');
                    dot.addEventListener('click', () => goTo(index));
                    dotsContainer.appendChild(dot);
                }
            };

            const update = () => {
                const visible = perView();
                pageCount = Math.max(1, Math.ceil(slides.length / visible));
                page = Math.min(page, pageCount - 1);
                if (controls) controls.hidden = pageCount <= 1;
                const slideIndex = Math.min(page * visible, Math.max(0, slides.length - visible));
                const gap = parseFloat(getComputedStyle(track).gap) || 0;
                const slideWidth = slides[0]?.getBoundingClientRect().width || 0;
                track.style.transform = `translateX(-${slideIndex * (slideWidth + gap)}px)`;
                renderDots();
            };

            const goTo = nextPage => {
                page = (nextPage + pageCount) % pageCount;
                update();
            };

            const startAutoPlay = () => {
                window.clearInterval(timer);
                if (!reduceMotion && pageCount > 1) timer = window.setInterval(() => goTo(page + 1), 6000);
            };

            previousButton?.addEventListener('click', () => { goTo(page - 1); startAutoPlay(); });
            nextButton?.addEventListener('click', () => { goTo(page + 1); startAutoPlay(); });
            carousel.addEventListener('mouseenter', () => window.clearInterval(timer));
            carousel.addEventListener('mouseleave', startAutoPlay);
            carousel.addEventListener('focusin', () => window.clearInterval(timer));
            carousel.addEventListener('focusout', startAutoPlay);
            carousel.addEventListener('touchstart', event => { touchStartX = event.touches[0].clientX; }, { passive: true });
            carousel.addEventListener('touchend', event => {
                if (touchStartX === null) return;
                const distance = event.changedTouches[0].clientX - touchStartX;
                if (Math.abs(distance) > 45) goTo(page + (distance < 0 ? 1 : -1));
                touchStartX = null;
                startAutoPlay();
            }, { passive: true });
            window.addEventListener('resize', update);

            update();
            startAutoPlay();
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animation = 'fadeInUp 0.6s ease-out forwards';
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        document.querySelectorAll('.service-card, .benefit-card, .pricing-card, .testimonial-card, .process-item').forEach(
            el => {
                el.style.opacity = '0';
                observer.observe(el);
            });

        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `
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
        `;
        document.head.appendChild(style);

        document.querySelectorAll('.dropdown-submenu > .dropdown-toggle').forEach(toggle => {
            toggle.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();

                const submenu = toggle.nextElementSibling;
                if (!submenu) {
                    return;
                }

                submenu.classList.toggle('show');
            });
        });

        const servicesLink = document.getElementById('servicesDropdown');
        const servicesOffcanvasEl = document.getElementById('servicesOffcanvas');
        const navbarCollapseEl = document.getElementById('navbarNav');
        let servicesOffcanvas = null;
        let navbarCollapse = null;

        if (navbarCollapseEl) {
            navbarCollapse = new bootstrap.Collapse(navbarCollapseEl, {
                toggle: false
            });
        }

        if (servicesLink && servicesOffcanvasEl) {
            servicesOffcanvas = new bootstrap.Offcanvas(servicesOffcanvasEl);
            servicesLink.addEventListener('click', event => {
                const toggler = document.querySelector('.navbar-toggler');
                const isMobileMenu = toggler && window.getComputedStyle(toggler).display !== 'none';

                if (isMobileMenu) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (navbarCollapseEl.classList.contains('show') && navbarCollapse) {
                        navbarCollapse.hide();
                    }
                    servicesOffcanvas.show();
                }
            });
        }

        document.querySelectorAll('.navbar .dropdown').forEach(dropdown => {
            dropdown.addEventListener('hidden.bs.dropdown', () => {
                dropdown.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                    menu.classList.remove('show');
                });
            });
        });

        // Active nav link on scroll
        const navLinks = document.querySelectorAll('.nav-link');
        window.addEventListener('scroll', () => {
            let current = '';
            document.querySelectorAll('section').forEach(section => {
                const sectionTop = section.offsetTop;
                if (pageYOffset >= sectionTop - 200) {
                    current = section.getAttribute('id');
                }
            });

            navLinks.forEach(link => {
                const href = link.getAttribute('href') || '';

                link.classList.remove('active');
                if (href.startsWith('#') && href.slice(1) === current) {
                    link.classList.add('active');
                }
            });
        });

        const couponRibbon = document.querySelector('[data-coupon-ribbon]');
        if (couponRibbon) {
            const countdownTarget = couponRibbon.querySelector('[data-coupon-countdown]');
            const offerEndsAt = new Date(couponRibbon.dataset.offerEndsAt || '').getTime();

            const renderCouponCountdown = () => {
                if (!countdownTarget || Number.isNaN(offerEndsAt)) {
                    couponRibbon.remove();
                    return;
                }

                const distance = offerEndsAt - Date.now();
                if (distance <= 0) {
                    couponRibbon.remove();
                    return;
                }

                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance / (1000 * 60 * 60)) % 24);
                const minutes = Math.floor((distance / (1000 * 60)) % 60);
                const seconds = Math.floor((distance / 1000) % 60);

                countdownTarget.textContent = `${days}d : ${hours}h : ${minutes}mins : ${seconds}sec`;
            };

            renderCouponCountdown();
            setInterval(renderCouponCountdown, 1000);
        }

        initTrademarkSearch();
    </script>
    @include('partials.disclaimer-consent')
</body>

</html>
