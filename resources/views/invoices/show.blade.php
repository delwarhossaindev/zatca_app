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
@endphp

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
    <div>
        <a href="{{ route('invoices.index') }}" class="text-decoration-none small"><i class="bi bi-arrow-left"></i> Invoices</a>
        <h1 class="h3 mb-0">{{ $invoice->invoice_number }} @include('invoices.partials.status', ['status' => $invoice->zatca_status])</h1>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if (! in_array($invoice->zatca_status, ['REPORTED', 'CLEARED']))
            <form method="POST" action="{{ route('invoices.submit', $invoice) }}">
                @csrf
                <button class="btn btn-success"><i class="bi bi-cloud-upload"></i> {{ $invoice->isSimplified() ? 'Report' : 'Clear' }} with ZATCA</button>
            </form>
        @endif
        <a href="{{ route('invoices.xml', $invoice) }}" class="btn btn-outline-secondary"><i class="bi bi-filetype-xml"></i> Signed XML</a>
        @if ($invoice->cleared_xml)
            <a href="{{ route('invoices.xml', [$invoice, 'cleared']) }}" class="btn btn-outline-secondary"><i class="bi bi-filetype-xml"></i> Cleared XML</a>
        @endif
        @if (! $invoice->isNote())
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Issue note</button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ route('invoices.create', ['original' => $invoice->id, 'document_type' => 'credit']) }}">Credit note</a></li>
                    <li><a class="dropdown-item" href="{{ route('invoices.create', ['original' => $invoice->id, 'document_type' => 'debit']) }}">Debit note</a></li>
                </ul>
            </div>
        @endif
        <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i></button>
    </div>
</div>

@if (! empty($validation['errorMessages']) || ! empty($validation['warningMessages']))
    <div class="card mb-4 no-print">
        <div class="card-header bg-white fw-semibold">ZATCA validation messages</div>
        <ul class="list-group list-group-flush small">
            @foreach ($validation['errorMessages'] ?? [] as $message)
                <li class="list-group-item text-danger"><i class="bi bi-x-circle"></i> <strong>{{ $message['code'] }}</strong>: {{ $message['message'] }}</li>
            @endforeach
            @foreach ($validation['warningMessages'] ?? [] as $message)
                <li class="list-group-item text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> <strong>{{ $message['code'] }}</strong>: {{ $message['message'] }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h2 class="h4 mb-1">{{ $invoice->documentLabel() }}</h2>
                <div class="text-muted">
                    <span dir="rtl">{{ match ($invoice->document_type) { 'credit' => 'إشعار دائن', 'debit' => 'إشعار مدين', default => $invoice->isSimplified() ? 'فاتورة ضريبية مبسطة' : 'فاتورة ضريبية' } }}</span>
                </div>
                <dl class="row small mt-3 mb-0">
                    <dt class="col-5">Invoice number</dt><dd class="col-7">{{ $invoice->invoice_number }}</dd>
                    <dt class="col-5">Issue date</dt><dd class="col-7">{{ $invoice->issue_date->format('Y-m-d') }} {{ $invoice->issue_time }}</dd>
                    @if ($invoice->isNote())
                        <dt class="col-5">Original invoice</dt><dd class="col-7">{{ $invoice->original_invoice_number }}</dd>
                        <dt class="col-5">Reason</dt><dd class="col-7">{{ $invoice->note_reason }}</dd>
                    @endif
                    <dt class="col-5">UUID</dt><dd class="col-7 mono">{{ $invoice->uuid }}</dd>
                </dl>
            </div>
            @if ($qrSvg)
                <div style="width: 170px">{!! $qrSvg !!}</div>
            @endif
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <h6 class="text-muted text-uppercase small">Seller</h6>
                <div class="fw-semibold">{{ $setting?->seller_name }}</div>
                <div class="small">VAT: {{ $setting?->vat_number }} @if ($setting?->crn) · CR: {{ $setting->crn }} @endif</div>
                <div class="small">{{ $setting?->building_number }} {{ $setting?->street }}, {{ $setting?->district }}, {{ $setting?->city }} {{ $setting?->postal_code }}</div>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted text-uppercase small">Buyer</h6>
                @if ($invoice->buyer_name)
                    <div class="fw-semibold">{{ $invoice->buyer_name }}</div>
                    @if ($invoice->buyer_vat_number)<div class="small">VAT: {{ $invoice->buyer_vat_number }}</div>@endif
                    @if ($invoice->buyer_street)
                        <div class="small">{{ $invoice->buyer_building_number }} {{ $invoice->buyer_street }}, {{ $invoice->buyer_district }}, {{ $invoice->buyer_city }} {{ $invoice->buyer_postal_code }}</div>
                    @endif
                @else
                    <div class="text-muted">Cash customer</div>
                @endif
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead class="table-light">
                <tr><th>#</th><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Net</th><th class="text-end">VAT</th><th class="text-end">Total</th></tr>
                </thead>
                <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $item->name }}</td>
                        <td class="text-end">{{ rtrim(rtrim(number_format($item->quantity, 6, '.', ''), '0'), '.') }}</td>
                        <td class="text-end">{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-end">{{ number_format($item->line_extension_amount, 2) }}</td>
                        <td class="text-end">{{ number_format($item->vat_amount, 2) }} <span class="text-muted small">({{ (float) $item->vat_percent }}%)</span></td>
                        <td class="text-end">{{ number_format($item->line_extension_amount + $item->vat_amount, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr><td colspan="6" class="text-end">Total excl. VAT</td><td class="text-end">{{ number_format($invoice->line_extension_amount, 2) }}</td></tr>
                <tr><td colspan="6" class="text-end">VAT</td><td class="text-end">{{ number_format($invoice->vat_amount, 2) }}</td></tr>
                <tr class="fw-bold"><td colspan="6" class="text-end">Total incl. VAT ({{ $invoice->currency }})</td><td class="text-end">{{ number_format($invoice->total_amount, 2) }}</td></tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="card mt-4 no-print">
    <div class="card-header bg-white fw-semibold">ZATCA data</div>
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
