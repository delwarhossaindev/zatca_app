@extends('layouts.app')

@section('title', 'Invoices')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Invoices</h1>
    <a href="{{ route('invoices.create') }}" class="btn btn-success @if (! $setting?->signingCertificate()) disabled @endif">
        <i class="bi bi-plus-lg"></i> New invoice
    </a>
</div>

@if (! $setting?->hasProductionCsid())
    <div class="alert alert-warning">
        <i class="bi bi-info-circle"></i>
        Complete <a href="{{ route('zatca.edit') }}" class="alert-link">ZATCA onboarding</a> to get a Production CSID before issuing invoices.
    </div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Number</th>
                <th>Type</th>
                <th>Date</th>
                <th>Buyer</th>
                <th class="text-end">Total (SAR)</th>
                <th>ICV</th>
                <th>ZATCA status</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($invoices as $invoice)
                <tr role="button" onclick="location.href='{{ route('invoices.show', $invoice) }}'">
                    <td class="fw-semibold">{{ $invoice->invoice_number }}</td>
                    <td>{{ $invoice->documentLabel() }}</td>
                    <td>{{ $invoice->issue_date->format('Y-m-d') }}</td>
                    <td>{{ $invoice->buyer_name ?: '—' }}</td>
                    <td class="text-end">{{ number_format($invoice->total_amount, 2) }}</td>
                    <td>{{ $invoice->icv }}</td>
                    <td>@include('invoices.partials.status', ['status' => $invoice->zatca_status])</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-5">No invoices yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $invoices->links('pagination::bootstrap-5') }}</div>
@endsection
