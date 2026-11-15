@extends('layouts.app')

@section('title', 'ZATCA Onboarding')

@php
    $saved = $setting->exists;
    $steps = [
        ['done' => $saved, 'title' => 'Seller & EGS details', 'arabic' => 'بيانات البائع والوحدة', 'icon' => 'shop'],
        ['done' => $setting->hasCsr(), 'title' => 'Generate key & CSR', 'arabic' => 'المفتاح وطلب الشهادة', 'icon' => 'key'],
        ['done' => $setting->hasComplianceCsid(), 'title' => 'Compliance CSID', 'arabic' => 'شهادة الامتثال', 'icon' => 'shield-check'],
        ['done' => filled($setting->compliance_checks) && collect($setting->compliance_checks)->every(fn ($r) => empty($r['errors'])), 'title' => 'Compliance checks', 'arabic' => 'فحوصات الامتثال', 'icon' => 'list-check'],
        ['done' => $setting->hasProductionCsid(), 'title' => 'Production CSID', 'arabic' => 'شهادة الإنتاج', 'icon' => 'patch-check'],
    ];
    $current = collect($steps)->search(fn ($step) => ! $step['done']);
@endphp

@push('head')
<style>
    .z-stepper { display: grid; grid-template-columns: repeat(5, 1fr); gap: .5rem; padding: 1.4rem 1rem 1.2rem; }
    .z-step { position: relative; text-align: center; }
    .z-step:not(:last-child)::after { content: ''; position: absolute; top: 23px; left: calc(50% + 30px); right: calc(-50% + 30px); height: 2px; background: var(--z-line); }
    .z-step.done:not(:last-child)::after { background: var(--z-green); }
    .z-step-dot { width: 48px; height: 48px; margin: 0 auto .6rem; display: grid; place-items: center; border-radius: 50%; font-size: 1.15rem; background: #fff; border: 2px solid var(--z-line); color: var(--z-muted); position: relative; z-index: 1; }
    .z-step.done .z-step-dot { background: var(--z-green); border-color: var(--z-green); color: #fff; }
    .z-step.current .z-step-dot { border-color: var(--z-gold); color: var(--z-green); box-shadow: 0 0 0 5px rgba(195, 154, 69, .2); }
    .z-step-title { font-size: .85rem; font-weight: 600; }
    .z-step:not(.done):not(.current) .z-step-title { color: var(--z-muted); font-weight: 500; }
    .z-step .ar { color: var(--z-gold); font-size: .78rem; text-align: center; display: block; }
    .z-step-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .06em; color: var(--z-muted); }
    @media (max-width: 767.98px) {
        .z-stepper { grid-template-columns: 1fr; text-align: start; }
        .z-step { display: flex; align-items: center; gap: .8rem; text-align: start; }
        .z-step::after { display: none; }
        .z-step-dot { margin: 0; width: 40px; height: 40px; flex: none; }
        .z-step .ar { text-align: left; }
    }
    .z-hint { font-size: .85rem; color: var(--z-muted); }
    .z-step-card.locked { opacity: .75; }
</style>
@endpush

@section('content')
<x-page-header title="ZATCA Phase 2 Onboarding" arabic="الربط مع منصة فاتورة · المرحلة الثانية" icon="shield-lock" />

<div class="card mb-4">
    <div class="z-stepper">
        @foreach ($steps as $i => $step)
            <div class="z-step {{ $step['done'] ? 'done' : '' }} {{ $i === $current ? 'current' : '' }}">
                <div class="z-step-dot">
                    @if ($step['done'])<i class="bi bi-check-lg"></i>@else<i class="bi bi-{{ $step['icon'] }}"></i>@endif
                </div>
                <div>
                    <div class="z-step-label">Step {{ $i + 1 }}</div>
                    <div class="z-step-title">{{ $step['title'] }}</div>
                    <span class="ar">{{ $step['arabic'] }}</span>
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('zatca.update') }}" class="card">
            @csrf @method('PUT')
            <div class="card-header"><span class="z-step-chip {{ $steps[0]['done'] ? 'done' : '' }}">1</span> Seller &amp; EGS unit details <span class="ar">بيانات البائع ووحدة الفوترة</span></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-globe2 text-muted"></i> Environment</label>
                        <select name="environment" class="form-select">
                            @foreach ($environments as $key => $env)
                                <option value="{{ $key }}" @selected(old('environment', $setting->environment) === $key)>{{ $env['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-ui-checks text-muted"></i> Invoice types</label>
                        <select name="invoice_types" class="form-select">
                            @foreach (['1100' => 'Standard + Simplified', '1000' => 'Standard (B2B) only', '0100' => 'Simplified (B2C) only'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('invoice_types', $setting->invoice_types) === $value)>{{ $label }} ({{ $value }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12"><div class="z-section-title"><i class="bi bi-shop"></i> Seller <span class="ar">البائع</span></div></div>
                    <div class="col-md-8">
                        <label class="form-label">Seller name (registration name)</label>
                        <input name="seller_name" class="form-control" value="{{ old('seller_name', $setting->seller_name) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">VAT number</label>
                        <input name="vat_number" class="form-control" value="{{ old('vat_number', $setting->vat_number) }}" placeholder="3XXXXXXXXXXXXX3" maxlength="15" required>
                        <div class="form-text">
                            Sandbox test VAT:
                            <a href="#" onclick="this.closest('div.col-md-4').querySelector('input').value = this.textContent.trim(); return false;" title="Use this number"><code>399999999900003</code></a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">CR number</label>
                        <input name="crn" class="form-control" value="{{ old('crn', $setting->crn) }}" maxlength="10">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Street</label>
                        <input name="street" class="form-control" value="{{ old('street', $setting->street) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Building no.</label>
                        <input name="building_number" class="form-control" value="{{ old('building_number', $setting->building_number) }}" maxlength="4" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Additional no.</label>
                        <input name="plot_identification" class="form-control" value="{{ old('plot_identification', $setting->plot_identification) }}" maxlength="4">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">District</label>
                        <input name="district" class="form-control" value="{{ old('district', $setting->district) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">City</label>
                        <input name="city" class="form-control" value="{{ old('city', $setting->city) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Postal code</label>
                        <input name="postal_code" class="form-control" value="{{ old('postal_code', $setting->postal_code) }}" maxlength="5" required>
                    </div>

                    <div class="col-12"><div class="z-section-title"><i class="bi bi-cpu"></i> EGS unit (CSR) <span class="ar">وحدة الفوترة</span></div></div>
                    <div class="col-md-6">
                        <label class="form-label">Common name (EGS name)</label>
                        <input name="egs_name" class="form-control" value="{{ old('egs_name', $setting->egs_name) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Branch / organization unit</label>
                        <input name="branch_name" class="form-control" value="{{ old('branch_name', $setting->branch_name) }}" placeholder="Riyadh Branch" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">EGS serial number</label>
                        <input name="egs_serial" class="form-control mono" value="{{ old('egs_serial', $setting->egs_serial) }}" required>
                        <div class="form-text">Format: 1-SolutionName|2-Model/Version|3-SerialNumber</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Industry / business category</label>
                        <input name="industry" class="form-control" value="{{ old('industry', $setting->industry) }}" placeholder="Retail" required>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <button class="btn btn-success px-4"><i class="bi bi-save"></i> Save settings <span class="ar small ms-1">حفظ</span></button>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3 z-step-card {{ $saved ? '' : 'locked' }}">
            <div class="card-header"><span class="z-step-chip {{ $steps[1]['done'] ? 'done' : '' }}">2</span> Private key &amp; CSR <span class="ar">المفتاح الخاص وطلب الشهادة</span></div>
            <div class="card-body">
                <p class="z-hint">Generates a secp256k1 key pair and a CSR with the ZATCA certificate template and EGS details.</p>
                @if ($setting->hasCsr())
                    <textarea class="form-control mono mb-2" rows="4" readonly>{{ $setting->csr }}</textarea>
                @endif
                <form method="POST" action="{{ route('zatca.csr') }}" onsubmit="return {{ $setting->hasCsr() ? 'confirm(\'Generating a new CSR removes the current certificates. Continue?\')' : 'true' }}">
                    @csrf
                    <button class="btn btn-outline-success" @disabled(! $saved)><i class="bi bi-key"></i> {{ $setting->hasCsr() ? 'Regenerate' : 'Generate' }} CSR</button>
                </form>
            </div>
        </div>

        <div class="card mb-3 z-step-card {{ $setting->hasCsr() ? '' : 'locked' }}">
            <div class="card-header"><span class="z-step-chip {{ $steps[2]['done'] ? 'done' : '' }}">3</span> Compliance CSID <span class="ar">شهادة الامتثال</span></div>
            <div class="card-body">
                <p class="z-hint mb-2">
                    Get the OTP from the Fatoora portal (<em>Onboard new solution unit</em>).
                    In the sandbox/developer portal the OTP is <code>{{ config('zatca.sandbox_otp') }}</code>.
                </p>
                <form method="POST" action="{{ route('zatca.compliance-csid') }}" class="d-flex gap-2">
                    @csrf
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-123"></i></span>
                        <input name="otp" class="form-control" placeholder="OTP" maxlength="6"
                               value="{{ old('otp', $setting->environment === 'sandbox' ? config('zatca.sandbox_otp') : '') }}" required>
                    </div>
                    <button class="btn btn-outline-success text-nowrap" @disabled(! $setting->hasCsr())><i class="bi bi-shield-check"></i> Get CSID</button>
                </form>
                @if ($setting->compliance_request_id)
                    <div class="small mt-2">Request ID: <span class="mono">{{ $setting->compliance_request_id }}</span></div>
                @endif
            </div>
        </div>

        <div class="card mb-3 z-step-card {{ $setting->hasComplianceCsid() ? '' : 'locked' }}">
            <div class="card-header"><span class="z-step-chip {{ $steps[3]['done'] ? 'done' : '' }}">4</span> Compliance checks <span class="ar">فحوصات الامتثال</span></div>
            <div class="card-body">
                <p class="z-hint">Signs and submits a sample of each required document (invoice, credit note, debit note) to the compliance API.</p>
                <form method="POST" action="{{ route('zatca.compliance-checks') }}">
                    @csrf
                    <button class="btn btn-outline-success" @disabled(! $setting->hasComplianceCsid())><i class="bi bi-list-check"></i> Run compliance checks</button>
                </form>
                @if ($setting->compliance_checks)
                    <ul class="list-group list-group-flush mt-3 small">
                        @foreach ($setting->compliance_checks as $label => $result)
                            <li class="list-group-item px-0">
                                <i class="bi {{ empty($result['errors']) ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }}"></i>
                                {{ $label }} — <strong>{{ $result['status'] }}</strong>
                                @foreach ($result['errors'] as $message)<div class="text-danger">{{ $message }}</div>@endforeach
                                @foreach ($result['warnings'] as $message)<div class="text-warning">{{ $message }}</div>@endforeach
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="card mb-3 z-step-card {{ $steps[3]['done'] ? '' : 'locked' }}">
            <div class="card-header"><span class="z-step-chip {{ $steps[4]['done'] ? 'done' : '' }}">5</span> Production CSID <span class="ar">شهادة الإنتاج</span></div>
            <div class="card-body">
                <form method="POST" action="{{ route('zatca.production-csid') }}">
                    @csrf
                    <button class="btn btn-success" @disabled(! $steps[3]['done'])><i class="bi bi-patch-check"></i> Get Production CSID</button>
                </form>
                @if ($certificate)
                    <dl class="small mt-3 mb-0">
                        <dt>Active certificate</dt><dd>{{ $setting->hasProductionCsid() ? 'Production' : 'Compliance' }}</dd>
                        <dt>Issuer</dt><dd class="mono">{{ $certificate->issuerName() }}</dd>
                        <dt>Valid until</dt><dd>{{ $certificate->validTo() }}</dd>
                        <dt>Invoice counter (ICV)</dt><dd>{{ $setting->icv }}</dd>
                    </dl>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
