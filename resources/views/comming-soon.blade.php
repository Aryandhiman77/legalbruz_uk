<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coming Soon - Legal Bruz Pvt. Ltd.</title>
    <link rel="icon" type="image/png" href="{{ asset('legal-bruz-pvt-ltd-logo.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #1D3557;
            --emerald: #2A9D8F;
            --slate: #4A4A4A;
            --light-bg: #F4F4F9;
            --white: #FFFFFF;
            --border: #E8E8EE;
            --accent: #FF6B35;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--slate);
            background: var(--light-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--navy);
            font-weight: 800;
        }

        .coming-soon-section {
            background: linear-gradient(135deg, var(--navy) 0%, #2d4a73 100%);
            color: var(--white);
            padding: 50px 20px;
            position: relative;
            overflow: hidden;
            width: 100%;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .coming-soon-section .container {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .coming-soon-section::before {
            content: '';
            position: absolute;
            top: -150px;
            right: -100px;
            width: 700px;
            height: 700px;
            background: rgba(42, 157, 143, 0.12);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }

        .coming-soon-section::after {
            content: '';
            position: absolute;
            bottom: -100px;
            left: -50px;
            width: 500px;
            height: 500px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            animation: float 10s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(30px);
            }
        }

        .coming-soon-content {
            position: relative;
            z-index: 2;
            text-align: center;
            max-width: 700px;
        }

        .coming-soon-content h1 {
            font-size: 3.5rem;
            font-weight: 900;
            line-height: 1.2;
            margin-bottom: 25px;
            letter-spacing: -1px;
            color: var(--white);
        }

        .coming-soon-content h1 span {
            background: linear-gradient(135deg, var(--emerald) 0%, #228974 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .coming-soon-subtitle {
            font-size: 1.25rem;
            opacity: 0.92;
            margin-bottom: 50px;
            font-weight: 400;
            line-height: 1.8;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin: 50px 0;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 30px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            text-align: center;
        }

        .feature-card:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-5px);
            border-color: var(--emerald);
        }

        .feature-card .icon {
            font-size: 40px;
            margin-bottom: 15px;
            display: block;
        }

        .feature-card .title {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: var(--white);
        }

        .feature-card .description {
            font-size: 0.95rem;
            opacity: 0.85;
            line-height: 1.6;
        }

        .email-form-section {
            margin: 50px 0;
        }

        .email-form-label {
            font-size: 1.1rem;
            margin-bottom: 20px;
            opacity: 0.95;
        }

        .email-form {
            display: flex;
            gap: 12px;
            max-width: 500px;
            margin: 0 auto;
            flex-wrap: wrap;
            justify-content: center;
        }

        .email-input {
            flex: 1;
            min-width: 250px;
            padding: 14px 20px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            background: var(--white);
            color: var(--slate);
        }

        .email-input::placeholder {
            color: #999;
        }

        .email-input:focus {
            outline: none;
            border-color: var(--emerald);
            box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.1);
        }

        .email-btn {
            padding: 14px 40px;
            background: linear-gradient(135deg, var(--emerald) 0%, #228974 100%);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 160px;
        }

        .email-btn:hover {
            box-shadow: 0 12px 30px rgba(42, 157, 143, 0.3);
            transform: translateY(-2px);
        }

        .email-btn:active {
            transform: translateY(0);
        }

        .social-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 40px 0;
        }

        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            text-decoration: none;
            color: var(--white);
            font-size: 1.3rem;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: var(--emerald);
            border-color: var(--emerald);
            transform: translateY(-3px);
        }

        .footer-text {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.95rem;
            margin-top: 40px;
        }

        @media (max-width: 768px) {
            .coming-soon-content h1 {
                font-size: 2.5rem;
            }

            .coming-soon-subtitle {
                font-size: 1.1rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .email-form {
                flex-direction: column;
            }

            .email-input {
                width: 100%;
            }

            .email-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <section class="coming-soon-section">
        <div class="container">
            <div class="coming-soon-content">
                <h1>Coming <span>Soon</span></h1>
                
                <p class="coming-soon-subtitle">
                    We're crafting something extraordinary. Legal Bruz is preparing to revolutionize trademark registration and IP protection in India. Stay tuned!
                </p>

                <div class="features-grid">
                    <div class="feature-card">
                        <span class="icon">⚡</span>
                        <div class="title">Lightning Fast</div>
                        <div class="description">Start your trademark in  minutes, not months</div>
                    </div>
                    <div class="feature-card">
                        <span class="icon">🔒</span>
                        <div class="title">Completely Secure</div>
                        <div class="description">Enterprise-grade protection for your data</div>
                    </div>
                    <div class="feature-card">
                        <span class="icon">✓</span>
                        <div class="title">Expert Guided</div>
                        <div class="description">Personalized support every step of the way</div>
                    </div>
                </div>

                <div class="email-form-section">
                    <p class="email-form-label">Get notified when we launch</p>
                    <form class="email-form" onsubmit="handleSubmit(event)">
                        <input 
                            type="email" 
                            class="email-input" 
                            placeholder="Enter your email address" 
                            required
                        >
                        <button type="submit" class="email-btn">Notify Me</button>
                    </form>
                </div>

                <div class="social-links" >
                    @foreach (config('social_links') as $social)
                        <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer"
                            title="{{ $social['label'] }}" aria-label="Legal Bruz on {{ $social['label'] }}">
                            <i class="bi {{ $social['icon'] }}" aria-hidden="true"></i>
                        </a>
                    @endforeach
                </div>

                <p class="footer-text">
                    &copy; 2026 Legal Bruz Pvt. Ltd. Revolutionizing IPR & Trademark Registration in India.
                </p>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function handleSubmit(e) {
            e.preventDefault();
            const email = e.target.querySelector('input[type="email"]').value;
            alert('Thank you for your interest! We will notify you at ' + email + ' when we launch.');
            e.target.reset();
        }
    </script>
</body>
</html>
