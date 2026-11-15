@extends('layouts.app')

@section('title', 'ZATCA Onboarding')

@php
    $saved = $setting->exists;
    $steps = [
        ['done' => $saved, 'title' => 'Seller & EGS details'],
        ['done' => $setting->hasCsr(), 'title' => 'Generate key & CSR'],
        ['done' => $setting->hasComplianceCsid(), 'title' => 'Compliance CSID'],
        ['done' => filled($setting->compliance_checks) && collect($setting->compliance_checks)->every(fn ($r) => empty($r['errors'])), 'title' => 'Compliance checks'],
        ['done' => $setting->hasProductionCsid(), 'title' => 'Production CSID'],
    ];
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">ZATCA Phase 2 Onboarding</h1>
</div>

<div class="card mb-4">
    <div class="card-body d-flex flex-wrap gap-4">
        @foreach ($steps as $i => $step)
            <div class="d-flex align-items-center gap-2">
                <span class="step-number {{ $step['done'] ? 'bg-success text-white' : 'bg-light border' }}">
                    @if ($step['done'])<i class="bi bi-check-lg"></i>@else{{ $i + 1 }}@endif
                </span>
                <span class="{{ $step['done'] ? 'fw-semibold' : 'text-muted' }}">{{ $step['title'] }}</span>
            </div>
        @endforeach
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('zatca.update') }}" class="card">
            @csrf @method('PUT')
            <div class="card-header bg-white fw-semibold">1. Seller & EGS unit details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Environment</label>
                        <select name="environment" class="form-select">
                            @foreach ($environments as $key => $env)
                                <option value="{{ $key }}" @selected(old('environment', $setting->environment) === $key)>{{ $env['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Invoice types</label>
                        <select name="invoice_types" class="form-select">
                            @foreach (['1100' => 'Standard + Simplified', '1000' => 'Standard (B2B) only', '0100' => 'Simplified (B2C) only'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('invoice_types', $setting->invoice_types) === $value)>{{ $label }} ({{ $value }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12"><h6 class="text-muted border-bottom pb-1 mt-2 mb-0">Seller</h6></div>
                    <div class="col-md-8">
                        <label class="form-label">Seller name (registration name)</label>
                        <input name="seller_name" class="form-control" value="{{ old('seller_name', $setting->seller_name) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">VAT number</label>
                        <input name="vat_number" class="form-control" value="{{ old('vat_number', $setting->vat_number) }}" placeholder="3XXXXXXXXXXXXX3" maxlength="15" required>
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

                    <div class="col-12"><h6 class="text-muted border-bottom pb-1 mt-2 mb-0">EGS unit (CSR)</h6></div>
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
            <div class="card-footer bg-white text-end">
                <button class="btn btn-success"><i class="bi bi-save"></i> Save settings</button>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">2. Private key & CSR</div>
            <div class="card-body">
                <p class="small text-muted">Generates a secp256k1 key pair and a CSR with the ZATCA certificate template and EGS details.</p>
                @if ($setting->hasCsr())
                    <textarea class="form-control mono mb-2" rows="4" readonly>{{ $setting->csr }}</textarea>
                @endif
                <form method="POST" action="{{ route('zatca.csr') }}" onsubmit="return {{ $setting->hasCsr() ? 'confirm(\'Generating a new CSR removes the current certificates. Continue?\')' : 'true' }}">
                    @csrf
                    <button class="btn btn-outline-success" @disabled(! $saved)><i class="bi bi-key"></i> {{ $setting->hasCsr() ? 'Regenerate' : 'Generate' }} CSR</button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">3. Compliance CSID</div>
            <div class="card-body">
                <p class="small text-muted mb-2">
                    Get the OTP from the Fatoora portal (<em>Onboard new solution unit</em>).
                    In the sandbox/developer portal the OTP is <code>{{ config('zatca.sandbox_otp') }}</code>.
                </p>
                <form method="POST" action="{{ route('zatca.compliance-csid') }}" class="d-flex gap-2">
                    @csrf
                    <input name="otp" class="form-control" placeholder="OTP" maxlength="6"
                           value="{{ old('otp', $setting->environment === 'sandbox' ? config('zatca.sandbox_otp') : '') }}" required>
                    <button class="btn btn-outline-success text-nowrap" @disabled(! $setting->hasCsr())><i class="bi bi-shield-check"></i> Get CSID</button>
                </form>
                @if ($setting->compliance_request_id)
                    <div class="small mt-2">Request ID: <span class="mono">{{ $setting->compliance_request_id }}</span></div>
                @endif
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">4. Compliance checks</div>
            <div class="card-body">
                <p class="small text-muted">Signs and submits a sample of each required document (invoice, credit note, debit note) to the compliance API.</p>
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

        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">5. Production CSID</div>
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
