<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ZATCA E-Invoicing') · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --z-green: #0d5640;
            --z-green-2: #127556;
            --z-green-dark: #08392b;
            --z-gold: #c39a45;
            --z-gold-soft: #f4ead2;
            --z-sand: #f7f2e8;
            --z-ink: #1c2925;
            --z-muted: #6b7570;
            --z-line: #e8e0cf;
            --z-radius: 14px;
            --bs-success: var(--z-green);
            --bs-success-rgb: 13, 86, 64;
            --bs-body-font-family: 'IBM Plex Sans Arabic', system-ui, -apple-system, 'Segoe UI', sans-serif;
            --bs-body-color: var(--z-ink);
            --bs-link-color: var(--z-green-2);
            --bs-link-color-rgb: 18, 117, 86;
            --bs-link-hover-color: var(--z-green);
            --z-pattern: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='72' height='72' viewBox='0 0 72 72'%3E%3Cg fill='none' stroke='%23c39a45' stroke-width='1'%3E%3Crect x='20' y='20' width='32' height='32'/%3E%3Crect x='20' y='20' width='32' height='32' transform='rotate(45 36 36)'/%3E%3Ccircle cx='36' cy='36' r='7'/%3E%3Cpath d='M0 0l13.4 13.4M72 0L58.6 13.4M0 72l13.4-13.4M72 72L58.6 58.6M36 0v13.4M36 72V58.6M0 36h13.4M72 36H58.6'/%3E%3C/g%3E%3C/svg%3E");
        }

        body {
            background: var(--z-sand);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: var(--z-pattern);
            opacity: .07;
            pointer-events: none;
            z-index: -1;
        }
        /* Arabic labels sit beside English ones in an LTR page, so blocks line up on the left like their English line */
        .ar { font-family: 'Reem Kufi', 'IBM Plex Sans Arabic', sans-serif; direction: rtl; unicode-bidi: isolate; text-align: left; }
        .text-end .ar { text-align: right; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .8rem; word-break: break-all; }

        /* Header */
        .z-nav {
            background: linear-gradient(120deg, var(--z-green-dark), var(--z-green) 55%, var(--z-green-2));
            position: relative;
            border-bottom: 3px solid var(--z-gold);
        }
        .z-nav::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: var(--z-pattern);
            opacity: .22;
            pointer-events: none;
        }
        .z-nav .container { position: relative; }
        .z-brand { display: flex; align-items: center; gap: .75rem; color: #fff; text-decoration: none; }
        .z-brand:hover { color: #fff; }
        .z-brand-mark {
            width: 42px; height: 42px;
            display: grid; place-items: center;
            background: rgba(255, 255, 255, .08);
            border: 1px solid rgba(195, 154, 69, .6);
            border-radius: 12px;
        }
        .z-brand-title { font-weight: 700; letter-spacing: .02em; line-height: 1.1; }
        .z-brand-sub { color: var(--z-gold); font-size: .85rem; line-height: 1.2; }
        .z-nav .nav-link {
            color: rgba(255, 255, 255, .78);
            border-radius: 10px;
            padding: .5rem .9rem !important;
            display: flex; align-items: center; gap: .45rem;
            transition: background .15s, color .15s;
        }
        .z-nav .nav-link:hover { color: #fff; background: rgba(255, 255, 255, .08); }
        .z-nav .nav-link.active { color: #fff; background: rgba(255, 255, 255, .14); box-shadow: inset 0 -2px 0 var(--z-gold); }
        .z-nav .nav-link .ar { font-size: .78rem; color: var(--z-gold); }
        .z-env {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .35rem .8rem;
            border-radius: 999px;
            font-size: .8rem;
            background: rgba(0, 0, 0, .18);
            border: 1px solid rgba(195, 154, 69, .55);
            color: #fff;
        }
        .z-env .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--z-gold); box-shadow: 0 0 0 3px rgba(195, 154, 69, .25); }
        .z-env.is-production .dot { background: #4ade80; box-shadow: 0 0 0 3px rgba(74, 222, 128, .25); }

        /* Page header */
        .z-page-head { margin-bottom: 1.75rem; }
        .z-page-head h1 { font-weight: 700; margin: 0; }
        .z-page-head .ar { color: var(--z-gold); font-size: 1.05rem; }
        .z-head-icon {
            width: 52px; height: 52px; flex: none;
            display: grid; place-items: center;
            border-radius: 16px;
            font-size: 1.45rem;
            color: var(--z-gold);
            background: linear-gradient(140deg, var(--z-green), var(--z-green-dark));
            box-shadow: 0 8px 20px -10px rgba(8, 57, 43, .7);
        }
        .z-ornament { display: flex; align-items: center; gap: .6rem; color: var(--z-gold); margin-top: .9rem; }
        .z-ornament::before, .z-ornament::after { content: ''; height: 1px; flex: 1; background: linear-gradient(90deg, transparent, var(--z-gold), transparent); }
        .z-back { color: var(--z-muted); font-size: .85rem; text-decoration: none; }
        .z-back:hover { color: var(--z-green); }

        /* Cards */
        .card {
            border: 1px solid var(--z-line);
            border-radius: var(--z-radius);
            box-shadow: 0 1px 2px rgba(28, 41, 37, .04), 0 10px 30px -18px rgba(28, 41, 37, .18);
            overflow: hidden;
        }
        .card-header {
            background: #fff;
            border-bottom: 1px solid var(--z-line);
            padding: .9rem 1.1rem;
            display: flex; flex-wrap: wrap; align-items: center; gap: .65rem;
            font-weight: 600;
        }
        .card-header .ar { margin-inline-start: auto; color: var(--z-gold); font-weight: 500; font-size: .95rem; }
        .card-footer { background: #fcfaf5; border-top: 1px solid var(--z-line); padding: .9rem 1.1rem; }
        .z-chip {
            width: 32px; height: 32px; flex: none;
            display: grid; place-items: center;
            border-radius: 10px;
            background: var(--z-gold-soft);
            color: var(--z-green);
        }
        .z-step-chip {
            width: 32px; height: 32px; flex: none;
            display: grid; place-items: center;
            border-radius: 50%;
            font-weight: 700; font-size: .9rem;
            border: 2px solid var(--z-gold);
            color: var(--z-green);
            background: #fff;
        }
        .z-step-chip.done { background: var(--z-green); border-color: var(--z-green); color: #fff; }

        /* Tables */
        .table { --bs-table-hover-bg: #faf6ec; margin-bottom: 0; }
        .table > thead th {
            background: #f8f3e7;
            color: var(--z-green);
            font-size: .78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .04em;
            border-bottom: 1px solid var(--z-line);
            white-space: nowrap;
        }
        .table > thead th .ar { display: block; text-transform: none; letter-spacing: 0; font-weight: 500; color: var(--z-gold); font-size: .78rem; }
        .table > :not(caption) > * > * { padding: .8rem 1rem; border-color: #efe8d8; }

        /* Forms */
        .form-label { font-weight: 500; font-size: .875rem; color: #3d4a45; }
        .form-control, .form-select { border-radius: 10px; border-color: #ddd4c1; }
        .form-control:focus, .form-select:focus { border-color: var(--z-gold); box-shadow: 0 0 0 .2rem rgba(195, 154, 69, .2); }
        .form-check-input:checked { background-color: var(--z-green); border-color: var(--z-green); }
        .form-check-input:focus { border-color: var(--z-gold); box-shadow: 0 0 0 .2rem rgba(195, 154, 69, .2); }
        .z-section-title { display: flex; align-items: center; gap: .5rem; color: var(--z-green); font-weight: 600; font-size: .9rem; padding-bottom: .4rem; border-bottom: 1px dashed var(--z-line); margin: .5rem 0 0; }
        .z-section-title .ar { margin-inline-start: auto; color: var(--z-gold); font-weight: 500; }

        /* Buttons */
        .btn { border-radius: 10px; font-weight: 500; }
        .btn-success {
            --bs-btn-bg: var(--z-green);
            --bs-btn-border-color: var(--z-green);
            --bs-btn-hover-bg: var(--z-green-dark);
            --bs-btn-hover-border-color: var(--z-green-dark);
            --bs-btn-active-bg: var(--z-green-dark);
            --bs-btn-active-border-color: var(--z-green-dark);
            --bs-btn-disabled-bg: var(--z-green);
            --bs-btn-disabled-border-color: var(--z-green);
            box-shadow: inset 0 -2px 0 rgba(195, 154, 69, .6);
        }
        .btn-outline-success {
            --bs-btn-color: var(--z-green);
            --bs-btn-border-color: var(--z-green);
            --bs-btn-hover-bg: var(--z-green);
            --bs-btn-hover-border-color: var(--z-green);
            --bs-btn-active-bg: var(--z-green-dark);
            --bs-btn-active-border-color: var(--z-green-dark);
            --bs-btn-disabled-color: var(--z-green);
            --bs-btn-disabled-border-color: var(--z-green);
        }
        .btn-outline-secondary { --bs-btn-color: #4b5752; --bs-btn-border-color: #d6ccb6; --bs-btn-hover-bg: var(--z-gold-soft); --bs-btn-hover-color: var(--z-green); --bs-btn-hover-border-color: var(--z-gold); --bs-btn-active-bg: var(--z-gold-soft); --bs-btn-active-color: var(--z-green); --bs-btn-active-border-color: var(--z-gold); background: #fff; }
        .btn-gold { --bs-btn-color: var(--z-green-dark); --bs-btn-bg: var(--z-gold); --bs-btn-border-color: var(--z-gold); --bs-btn-hover-bg: #b08938; --bs-btn-hover-border-color: #b08938; --bs-btn-hover-color: #fff; --bs-btn-active-bg: #9d7a31; --bs-btn-active-border-color: #9d7a31; --bs-btn-active-color: #fff; font-weight: 600; }

        /* Status pills */
        .z-status { display: inline-flex; align-items: center; gap: .35rem; padding: .3rem .7rem; border-radius: 999px; font-size: .75rem; font-weight: 600; letter-spacing: .02em; white-space: nowrap; vertical-align: middle; }
        .z-status.ok { background: #e3f3ea; color: #0d5640; }
        .z-status.bad { background: #fbe6e4; color: #a12a22; }
        .z-status.wait { background: #eeece6; color: #5b625e; }
        .z-status.warn { background: #fbf0d6; color: #8a6414; }

        /* Alerts & pagination */
        .alert { border-radius: 12px; border-width: 1px; display: flex; gap: .6rem; align-items: flex-start; }
        .alert-success { background: #e8f4ed; border-color: #bfe0cc; color: var(--z-green-dark); }
        .alert-warning { background: #fbf3df; border-color: #ecd9a6; color: #6e5212; }
        .pagination { --bs-pagination-active-bg: var(--z-green); --bs-pagination-active-border-color: var(--z-green); --bs-pagination-color: var(--z-green); --bs-pagination-hover-color: var(--z-green-dark); --bs-pagination-border-radius: 10px; }

        .z-footer { margin-top: auto; padding: 1.5rem 0; color: var(--z-muted); font-size: .8rem; border-top: 1px solid var(--z-line); background: rgba(255, 255, 255, .5); }
        .z-footer .ar { color: var(--z-gold); }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            body::before { display: none; }
            .card { box-shadow: none; }
            main { padding: 0 !important; }
        }
    </style>
    @stack('head')
</head>
<body>
@php
    $envKey = \App\Models\ZatcaSetting::current()?->environment ?? config('zatca.env');
@endphp
<nav class="navbar navbar-expand-md navbar-dark z-nav py-3 no-print">
    <div class="container">
        <a class="z-brand me-4" href="{{ route('invoices.index') }}">
            <span class="z-brand-mark">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#c39a45" stroke-width="1.6" aria-hidden="true">
                    <rect x="5" y="5" width="14" height="14" rx="1"/>
                    <rect x="5" y="5" width="14" height="14" rx="1" transform="rotate(45 12 12)"/>
                    <circle cx="12" cy="12" r="2.6" fill="#c39a45" stroke="none"/>
                </svg>
            </span>
            <span>
                <span class="z-brand-title d-block">ZATCA E-Invoicing</span>
                <span class="z-brand-sub ar d-block text-start">الفوترة الإلكترونية</span>
            </span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto gap-1 mt-3 mt-md-0">
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('invoices.*')) active @endif" href="{{ route('invoices.index') }}">
                        <i class="bi bi-receipt-cutoff"></i> Invoices <span class="ar">الفواتير</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('zatca.*')) active @endif" href="{{ route('zatca.edit') }}">
                        <i class="bi bi-shield-lock"></i> ZATCA Onboarding <span class="ar">الربط مع فاتورة</span>
                    </a>
                </li>
            </ul>
            <span class="z-env {{ $envKey === 'production' ? 'is-production' : '' }} mt-2 mt-md-0">
                <span class="dot"></span> {{ config('zatca.environments.'.$envKey.'.label') }}
            </span>
        </div>
    </div>
</nav>

<main class="container py-4 py-lg-5">
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show no-print"><i class="bi bi-check-circle-fill"></i> <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show no-print"><i class="bi bi-exclamation-octagon-fill"></i> <div>{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger no-print"><i class="bi bi-exclamation-octagon-fill"></i>
            <ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @yield('content')
</main>

<footer class="z-footer no-print">
    <div class="container d-flex flex-wrap justify-content-between gap-2">
        <span><i class="bi bi-shield-check"></i> ZATCA Phase 2 · Fatoora e-invoicing</span>
        <span class="ar">هيئة الزكاة والضريبة والجمارك · المرحلة الثانية</span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
