<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Legal Bruz Pvt. Ltd.') }} - Admin Login</title>
    <link rel="icon" type="image/png" href="{{ asset('legal-bruz-pvt-ltd-logo.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

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
            font-family: 'Inter', sans-serif;
            background: var(--light-bg);
            color: var(--slate);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--navy);
        }

        .btn-primary {
            background: var(--navy);
            border-color: var(--navy);
        }

        .btn-primary:hover {
            background: var(--emerald);
            border-color: var(--emerald);
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/mobile-typography.css') }}">
</head>

<body>
    @yield('content')

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    @include('partials.button-loading')

    @stack('scripts')
</body>

</html>
