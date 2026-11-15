@extends('layouts.app')

@section('title', $invoice->invoice_number)

@php
    $qrSvg = $invoice->qr_code
        ? (new \chillerlan\QRCode\QRCode(new \chillerlan\QRCode\QROptions([
            'outputBase64' => false,
            'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M,
            'addQuietzone' => true,
        ])))->render($invoice->qr_code)
        : null;
    $response = $invoice->zatca_response ?? [];
    $validation = $response['validationResults'] ?? [];
    $arabicTitle = match ($invoice->document_type) {
        'credit' => 'إشعار دائن',
        'debit' => 'إشعار مدين',
        default => $invoice->isSimplified() ? 'فاتورة ضريبية مبسطة' : 'فاتورة ضريبية',
    };
@endphp

@push('head')
<style>
    .z-invoice { position: relative; }
    .z-invoice::before { content: ''; position: absolute; inset: 0 0 auto; height: 6px; background: linear-gradient(90deg, var(--z-green-dark), var(--z-gold), var(--z-green-dark)); }
    .z-invoice-band { background: linear-gradient(180deg, #fbf7ee, #fff); border-bottom: 1px solid var(--z-line); }
    .z-invoice-title .ar { font-size: 1.6rem; color: var(--z-green); font-weight: 700; }
    .z-qr { width: 150px; padding: 6px; border: 1px solid var(--z-line); border-radius: 12px; background: #fff; }
    .z-meta dt { font-weight: 500; color: var(--z-muted); font-size: .8rem; }
    .z-meta dd { font-weight: 600; margin-bottom: .55rem; }
    .z-meta .ar { font-weight: 500; color: var(--z-gold); font-size: .78rem; }
    .z-party { border: 1px solid var(--z-line); border-radius: 12px; padding: 1rem 1.1rem; height: 100%; background: #fff; }
    .z-party-head { display: flex; justify-content: space-between; align-items: center; color: var(--z-green); font-weight: 600; font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .6rem; }
    .z-party-head .ar { color: var(--z-gold); text-transform: none; letter-spacing: 0; font-size: .9rem; }
    .z-sum { border: 1px solid var(--z-line); border-radius: 12px; overflow: hidden; }
    .z-sum > div { display: flex; justify-content: space-between; gap: 1rem; padding: .6rem 1rem; font-size: .9rem; }
    .z-sum > div + div { border-top: 1px solid var(--z-line); }
    .z-sum .ar { color: var(--z-gold); font-size: .8rem; margin-inline-start: .35rem; }
    .z-sum .grand { background: linear-gradient(120deg, var(--z-green-dark), var(--z-green)); color: #fff; font-weight: 700; font-size: 1.05rem; }
    /* Phones: each line item becomes a block of label / value rows instead of a 7-column table */
    @media screen and (max-width: 575.98px) {
        .z-items thead { display: none; }
        .z-items, .z-items tbody, .z-items tr, .z-items td { display: block; width: 100%; }
        .z-items tr { padding: .8rem 1rem; border-bottom: 1px solid var(--z-line); }
        .z-items tr:last-child { border-bottom: 0; }
        .z-items td { display: flex; justify-content: space-between; gap: 1rem; padding: .15rem 0 !important; border: 0; text-align: end !important; }
        .z-items td::before { content: attr(data-label); color: var(--z-muted); font-size: .8rem; font-weight: 500; text-align: start; }
        .z-items td.z-item-num { display: none; }
        .z-items td.z-item-name { padding-bottom: .4rem !important; text-align: start !important; }
        .z-items td.z-item-name::before { content: none; }
        .z-sum .ar { display: block; margin-inline-start: 0; }
        .z-invoice-band { padding: 1.25rem !important; padding-top: 1.75rem !important; }
        .z-invoice .card-body { padding: 1rem !important; }
        .z-qr { width: 130px; }
    }
    @media print {
        .z-invoice-band, .z-sum .grand { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .z-invoice::before { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
@endpush

@section('content')
<x-page-header :title="$invoice->invoice_number" :arabic="$arabicTitle" icon="receipt" :back="route('invoices.index')" back-label="Invoices">
    @include('invoices.partials.status', ['status' => $invoice->zatca_status])
    @if (! in_array($invoice->zatca_status, ['REPORTED', 'CLEARED']))
        <form method="POST" action="{{ route('invoices.submit', $invoice) }}">
            @csrf
            <button class="btn btn-success"><i class="bi bi-cloud-arrow-up"></i> {{ $invoice->isSimplified() ? 'Report' : 'Clear' }} with ZATCA</button>
        </form>
    @endif
    <a href="{{ route('invoices.xml', $invoice) }}" class="btn btn-outline-secondary"><i class="bi bi-filetype-xml"></i> Signed XML</a>
    @if ($invoice->cleared_xml)
        <a href="{{ route('invoices.xml', [$invoice, 'cleared']) }}" class="btn btn-outline-secondary"><i class="bi bi-filetype-xml"></i> Cleared XML</a>
    @endif
    @if (! $invoice->isNote())
        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-journal-plus"></i> Issue note</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('invoices.create', ['original' => $invoice->id, 'document_type' => 'credit']) }}"><i class="bi bi-arrow-counterclockwise"></i> Credit note <span class="ar small text-muted">إشعار دائن</span></a></li>
                <li><a class="dropdown-item" href="{{ route('invoices.create', ['original' => $invoice->id, 'document_type' => 'debit']) }}"><i class="bi bi-plus-slash-minus"></i> Debit note <span class="ar small text-muted">إشعار مدين</span></a></li>
            </ul>
        </div>
    @endif
    <button onclick="window.print()" class="btn btn-gold" title="Print"><i class="bi bi-printer"></i></button>
</x-page-header>

@if (! empty($validation['errorMessages']) || ! empty($validation['warningMessages']))
    <div class="card mb-4 no-print">
        <div class="card-header"><span class="z-chip"><i class="bi bi-clipboard2-pulse"></i></span> ZATCA validation messages <span class="ar">رسائل التحقق</span></div>
        <ul class="list-group list-group-flush small">
            @foreach ($validation['errorMessages'] ?? [] as $message)
                <li class="list-group-item text-danger"><i class="bi bi-x-circle-fill"></i> <strong>{{ $message['code'] }}</strong>: {{ $message['message'] }}</li>
            @endforeach
            @foreach ($validation['warningMessages'] ?? [] as $message)
                <li class="list-group-item text-warning-emphasis"><i class="bi bi-exclamation-triangle-fill"></i> <strong>{{ $message['code'] }}</strong>: {{ $message['message'] }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card z-invoice">
    <div class="z-invoice-band p-4 pt-5">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-4">
            <div class="z-invoice-title">
                <div class="ar text-start">{{ $arabicTitle }}</div>
                <h2 class="h5 text-muted fw-semibold mb-3">{{ $invoice->documentLabel() }}</h2>
                <dl class="z-meta row mb-0" style="max-width: 520px">
                    <dt class="col-5">Invoice number <span class="ar d-block">رقم الفاتورة</span></dt><dd class="col-7">{{ $invoice->invoice_number }}</dd>
                    <dt class="col-5">Issue date <span class="ar d-block">تاريخ الإصدار</span></dt><dd class="col-7">{{ $invoice->issue_date->format('Y-m-d') }} {{ $invoice->issue_time }}</dd>
                    @if ($invoice->isNote())
                        <dt class="col-5">Original invoice <span class="ar d-block">الفاتورة الأصلية</span></dt><dd class="col-7">{{ $invoice->original_invoice_number }}</dd>
                        <dt class="col-5">Reason <span class="ar d-block">السبب</span></dt><dd class="col-7">{{ $invoice->note_reason }}</dd>
                    @endif
                    <dt class="col-5">UUID <span class="ar d-block">المعرّف الفريد</span></dt><dd class="col-7 mono fw-normal">{{ $invoice->uuid }}</dd>
                </dl>
            </div>
            @if ($qrSvg)
                <div class="z-qr">{!! $qrSvg !!}</div>
            @endif
        </div>
    </div>

    <div class="card-body p-4">
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="z-party">
                    <div class="z-party-head"><span><i class="bi bi-shop"></i> Seller</span><span class="ar">البائع</span></div>
                    <div class="fw-semibold">{{ $setting?->seller_name }}</div>
                    <div class="small">VAT: {{ $setting?->vat_number }} @if ($setting?->crn) · CR: {{ $setting->crn }} @endif</div>
                    <div class="small text-muted"><i class="bi bi-geo-alt"></i> {{ $setting?->building_number }} {{ $setting?->street }}, {{ $setting?->district }}, {{ $setting?->city }} {{ $setting?->postal_code }}</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="z-party">
                    <div class="z-party-head"><span><i class="bi bi-person"></i> Buyer</span><span class="ar">المشتري</span></div>
                    @if ($invoice->buyer_name)
                        <div class="fw-semibold">{{ $invoice->buyer_name }}</div>
                        @if ($invoice->buyer_vat_number)<div class="small">VAT: {{ $invoice->buyer_vat_number }}</div>@endif
                        @if ($invoice->buyer_street)
                            <div class="small text-muted"><i class="bi bi-geo-alt"></i> {{ $invoice->buyer_building_number }} {{ $invoice->buyer_street }}, {{ $invoice->buyer_district }}, {{ $invoice->buyer_city }} {{ $invoice->buyer_postal_code }}</div>
                        @endif
                    @else
                        <div class="text-muted">Cash customer <span class="ar">عميل نقدي</span></div>
                    @endif
                </div>
            </div>
        </div>

        <div class="table-responsive border rounded-3 mb-4" style="border-color: var(--z-line) !important">
            <table class="table z-items">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Description <span class="ar">الوصف</span></th>
                    <th class="text-end">Qty <span class="ar">الكمية</span></th>
                    <th class="text-end">Unit price <span class="ar">سعر الوحدة</span></th>
                    <th class="text-end">Net <span class="ar">الصافي</span></th>
                    <th class="text-end">VAT <span class="ar">الضريبة</span></th>
                    <th class="text-end">Total <span class="ar">الإجمالي</span></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td class="text-muted z-item-num">{{ $loop->iteration }}</td>
                        <td class="fw-semibold z-item-name">{{ $item->name }}</td>
                        <td class="text-end" data-label="Qty · الكمية">{{ rtrim(rtrim(number_format($item->quantity, 6, '.', ''), '0'), '.') }}</td>
                        <td class="text-end" data-label="Unit price · سعر الوحدة">{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-end" data-label="Net · الصافي">{{ number_format($item->line_extension_amount, 2) }}</td>
                        <td class="text-end" data-label="VAT · الضريبة"><span>{{ number_format($item->vat_amount, 2) }} <span class="text-muted small">({{ (float) $item->vat_percent }}%)</span></span></td>
                        <td class="text-end fw-semibold" data-label="Total · الإجمالي">{{ number_format($item->line_extension_amount + $item->vat_amount, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="row justify-content-end">
            <div class="col-md-7 col-lg-5">
                <div class="z-sum">
                    <div><span>Total excl. VAT <span class="ar">الإجمالي غير شامل الضريبة</span></span><span>{{ number_format($invoice->line_extension_amount, 2) }}</span></div>
                    <div><span>VAT <span class="ar">ضريبة القيمة المضافة</span></span><span>{{ number_format($invoice->vat_amount, 2) }}</span></div>
                    <div class="grand"><span>Total incl. VAT ({{ $invoice->currency }}) <span class="ar">الإجمالي شامل الضريبة</span></span><span>{{ number_format($invoice->total_amount, 2) }}</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4 no-print">
    <div class="card-header"><span class="z-chip"><i class="bi bi-fingerprint"></i></span> ZATCA data <span class="ar">بيانات زاتكا</span></div>
    <div class="card-body small">
        <dl class="row mb-0">
            <dt class="col-md-3">ICV</dt><dd class="col-md-9">{{ $invoice->icv }}</dd>
            <dt class="col-md-3">Invoice hash</dt><dd class="col-md-9 mono">{{ $invoice->invoice_hash }}</dd>
            <dt class="col-md-3">Previous invoice hash</dt><dd class="col-md-9 mono">{{ $invoice->previous_invoice_hash }}</dd>
            <dt class="col-md-3">Submitted at</dt><dd class="col-md-9">{{ $invoice->submitted_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>
            <dt class="col-md-3">QR (TLV base64)</dt><dd class="col-md-9 mono">{{ $invoice->qr_code }}</dd>
        </dl>
    </div>
</div>
@endsection
