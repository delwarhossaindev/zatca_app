@extends('layouts.app')

@section('title', 'Invoices')

@push('head')
<style>
    .z-stat { padding: 1.1rem 1.2rem; flex-direction: row; align-items: center; gap: 1rem; height: 100%; }
    .z-stat-icon { width: 46px; height: 46px; flex: none; display: grid; place-items: center; border-radius: 14px; font-size: 1.25rem; }
    .z-stat-value { font-size: 1.6rem; font-weight: 700; line-height: 1; }
    .z-stat-label { font-size: .8rem; color: var(--z-muted); }
    .z-stat-label.ar { color: var(--z-gold); }
    .z-doc-icon { width: 36px; height: 36px; flex: none; display: grid; place-items: center; border-radius: 10px; background: var(--z-gold-soft); color: var(--z-green); }
    .z-empty { padding: 4rem 1rem; text-align: center; }
    .z-empty-icon { width: 76px; height: 76px; margin: 0 auto 1rem; display: grid; place-items: center; border-radius: 22px; font-size: 2rem; color: var(--z-gold); background: linear-gradient(140deg, var(--z-green), var(--z-green-dark)); }
</style>
@endpush

@section('content')
<x-page-header title="Invoices" arabic="الفواتير" icon="receipt-cutoff">
    <a href="{{ route('invoices.create') }}" class="btn btn-success px-3 @if (! $setting?->signingCertificate()) disabled @endif">
        <i class="bi bi-plus-lg"></i> New invoice <span class="ar small ms-1">فاتورة جديدة</span>
    </a>
</x-page-header>

@if (! $setting?->hasProductionCsid())
    <div class="alert alert-warning">
        <i class="bi bi-info-circle-fill"></i>
        <div>Complete <a href="{{ route('zatca.edit') }}" class="alert-link">ZATCA onboarding</a> to get a Production CSID before issuing invoices.</div>
    </div>
@endif

<div class="row g-3 mb-4">
    @foreach ([
        ['All documents', 'جميع المستندات', $stats['all'], 'bi-files', 'background: var(--z-gold-soft); color: var(--z-green);'],
        ['Accepted', 'مقبولة', $stats['accepted'], 'bi-patch-check-fill', 'background: #e3f3ea; color: #0d5640;'],
        ['Pending', 'قيد الانتظار', $stats['pending'], 'bi-hourglass-split', 'background: #eeece6; color: #5b625e;'],
        ['Rejected', 'مرفوضة', $stats['rejected'], 'bi-x-octagon-fill', 'background: #fbe6e4; color: #a12a22;'],
    ] as [$label, $arabic, $count, $icon, $style])
        <div class="col-6 col-lg-3">
            <div class="card z-stat">
                <span class="z-stat-icon" style="{{ $style }}"><i class="bi {{ $icon }}"></i></span>
                <div>
                    <div class="z-stat-value">{{ number_format($count) }}</div>
                    <div class="z-stat-label mt-1">{{ $label }}</div>
                    <div class="z-stat-label ar">{{ $arabic }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>Number <span class="ar">الرقم</span></th>
                <th>Type <span class="ar">النوع</span></th>
                <th>Date <span class="ar">التاريخ</span></th>
                <th>Buyer <span class="ar">المشتري</span></th>
                <th class="text-end">Total (SAR) <span class="ar">الإجمالي</span></th>
                <th>ICV <span class="ar">العداد</span></th>
                <th>ZATCA status <span class="ar">الحالة</span></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($invoices as $invoice)
                <tr role="button" onclick="location.href='{{ route('invoices.show', $invoice) }}'">
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="z-doc-icon"><i class="bi {{ match ($invoice->document_type) { 'credit' => 'bi-arrow-counterclockwise', 'debit' => 'bi-plus-slash-minus', default => 'bi-receipt' } }}"></i></span>
                            <span class="fw-semibold">{{ $invoice->invoice_number }}</span>
                        </div>
                    </td>
                    <td class="small">{{ $invoice->documentLabel() }}</td>
                    <td class="small text-nowrap"><i class="bi bi-calendar3 text-muted"></i> {{ $invoice->issue_date->format('Y-m-d') }}</td>
                    <td>{{ $invoice->buyer_name ?: '—' }}</td>
                    <td class="text-end fw-semibold">{{ number_format($invoice->total_amount, 2) }}</td>
                    <td class="text-muted">#{{ $invoice->icv }}</td>
                    <td>@include('invoices.partials.status', ['status' => $invoice->zatca_status])</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="z-empty">
                        <div class="z-empty-icon"><i class="bi bi-receipt"></i></div>
                        <div class="fw-semibold">No invoices yet</div>
                        <div class="ar text-center" style="color: var(--z-gold)">لا توجد فواتير بعد</div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $invoices->links('pagination::bootstrap-5') }}</div>
@endsection
