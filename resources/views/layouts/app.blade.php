<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ZATCA E-Invoicing') · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .navbar-brand { font-weight: 600; letter-spacing: .02em; }
        .card { border: 0; box-shadow: 0 1px 3px rgba(0, 0, 0, .08); }
        .step-number { width: 2rem; height: 2rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .8rem; word-break: break-all; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .card { box-shadow: none; }
        }
    </style>
    @stack('head')
</head>
<body>
<nav class="navbar navbar-expand-md navbar-dark bg-success no-print">
    <div class="container">
        <a class="navbar-brand" href="{{ route('invoices.index') }}"><i class="bi bi-receipt"></i> ZATCA E-Invoicing</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link @if(request()->routeIs('invoices.*')) active @endif" href="{{ route('invoices.index') }}">Invoices</a></li>
                <li class="nav-item"><a class="nav-link @if(request()->routeIs('zatca.*')) active @endif" href="{{ route('zatca.edit') }}">ZATCA Onboarding</a></li>
            </ul>
            <span class="navbar-text small">
                <i class="bi bi-globe2"></i> {{ config('zatca.environments.'.(\App\Models\ZatcaSetting::current()?->environment ?? config('zatca.env')).'.label') }}
            </span>
        </div>
    </div>
</nav>

<main class="container py-4">
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show no-print"><i class="bi bi-check-circle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show no-print"><i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger no-print">
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
