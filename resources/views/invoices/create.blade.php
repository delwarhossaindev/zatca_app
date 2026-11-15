@extends('layouts.app')

@section('title', 'New invoice')

@php
    $items = old('items', $original
        ? $original->items->map->only(['name', 'quantity', 'unit_price'])->all()
        : [['name' => '', 'quantity' => 1, 'unit_price' => '']]);
    $value = fn ($field, $default = null) => old($field, $original?->{$field} ?? $default);
    [$pageTitle, $pageArabic, $pageIcon] = match ($documentType) {
        'credit' => ['New credit note', 'إشعار دائن جديد', 'arrow-counterclockwise'],
        'debit' => ['New debit note', 'إشعار مدين جديد', 'plus-slash-minus'],
        default => ['New invoice', 'فاتورة ضريبية جديدة', 'file-earmark-plus'],
    };
@endphp

@push('head')
<style>
    .z-totals { border: 1px solid var(--z-line); border-radius: 12px; overflow: hidden; }
    .z-totals > div { padding: .55rem .9rem; font-size: .9rem; }
    .z-totals > div + div { border-top: 1px solid var(--z-line); }
    .z-totals .ar { color: var(--z-gold); margin-inline-start: .35rem; }
    .z-totals-grand { background: linear-gradient(120deg, var(--z-green-dark), var(--z-green)); color: #fff; font-weight: 700; font-size: 1.05rem !important; }
    /* Phones: each item becomes a small block (description, then qty / price / remove, then its total) */
    @media (max-width: 767.98px) {
        #items thead { display: none; }
        #items, #items tbody { display: block; }
        #items tr { display: grid; grid-template-columns: 1fr 1fr auto; gap: .5rem; align-items: end; padding: .9rem 1rem; border-bottom: 1px solid var(--z-line); }
        #items td { display: block; padding: 0; border: 0; }
        #items td:first-child { grid-column: 1 / -1; }
        #items td.line-total { grid-column: 1 / -1; order: 1; display: flex; justify-content: space-between; text-align: start !important; }
        #items td[data-label]::before { content: attr(data-label); display: block; font-size: .75rem; font-weight: 500; color: var(--z-muted); margin-bottom: .2rem; }
        #items td.line-total::before { margin: 0; }
        #items .remove { height: calc(1.5em + .75rem + 2px); }
    }
    @media (max-width: 575.98px) {
        .z-totals .ar { display: block; margin-inline-start: 0; }
        .card-footer .btn-success { width: 100%; }
    }
</style>
@endpush

@section('content')
<x-page-header :title="$pageTitle" :arabic="$pageArabic" :icon="$pageIcon" :back="route('invoices.index')" back-label="Invoices" />

<form method="POST" action="{{ route('invoices.store') }}" id="invoice-form">
    @csrf
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><span class="z-chip"><i class="bi bi-file-earmark-text"></i></span> Document <span class="ar">المستند</span></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Invoice type</label>
                        <select name="invoice_type" id="invoice_type" class="form-select">
                            @if (! $setting || $setting->invoice_types[1] === '1')
                                <option value="simplified" @selected($value('invoice_type') === 'simplified')>Simplified (B2C) — reported</option>
                            @endif
                            @if (! $setting || $setting->invoice_types[0] === '1')
                                <option value="standard" @selected($value('invoice_type') === 'standard')>Standard (B2B) — cleared</option>
                            @endif
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Document</label>
                        <select name="document_type" id="document_type" class="form-select">
                            @foreach (['invoice' => 'Tax invoice (388)', 'credit' => 'Credit note (381)', 'debit' => 'Debit note (383)'] as $key => $label)
                                <option value="{{ $key }}" @selected(old('document_type', $documentType) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Issue date</label>
                        <input type="date" name="issue_date" class="form-control" value="{{ old('issue_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment means</label>
                        <select name="payment_means" class="form-select">
                            @foreach (['10' => 'Cash', '30' => 'Credit', '42' => 'Bank account', '48' => 'Bank card', '1' => 'Not defined'] as $code => $label)
                                <option value="{{ $code }}" @selected(old('payment_means', '10') == $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="note-fields">
                        <div class="mb-3">
                            <label class="form-label">Original invoice number</label>
                            <input name="original_invoice_number" class="form-control" value="{{ old('original_invoice_number', $original?->invoice_number) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason for the note</label>
                            <input name="note_reason" class="form-control" value="{{ old('note_reason') }}" placeholder="e.g. Returned items">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <span class="z-chip"><i class="bi bi-person-vcard"></i></span>
                    <span>Buyer <span class="text-muted small fw-normal buyer-optional">(optional for simplified)</span></span>
                    <span class="ar">المشتري</span>
                </div>
                <div class="card-body">
                    <div class="mb-2"><label class="form-label small">Name</label><input name="buyer_name" class="form-control" value="{{ $value('buyer_name') }}"></div>
                    <div class="mb-2">
                        <label class="form-label small">VAT number</label><input name="buyer_vat_number" class="form-control" maxlength="15" placeholder="3XXXXXXXXXXXXX3" value="{{ $value('buyer_vat_number') }}">
                        <div class="form-text">
                            Sandbox test VAT:
                            <a href="#" onclick="this.closest('div.mb-2').querySelector('input').value = this.textContent.trim(); return false;" title="Use this number"><code>311111111111113</code></a>
                        </div>
                    </div>
                    <div class="mb-2"><label class="form-label small">Street</label><input name="buyer_street" class="form-control" value="{{ $value('buyer_street') }}"></div>
                    <div class="row g-2 mb-2">
                        <div class="col"><label class="form-label small">Building no.</label><input name="buyer_building_number" class="form-control" maxlength="4" value="{{ $value('buyer_building_number') }}"></div>
                        <div class="col"><label class="form-label small">Postal code</label><input name="buyer_postal_code" class="form-control" maxlength="5" value="{{ $value('buyer_postal_code') }}"></div>
                    </div>
                    <div class="row g-2">
                        <div class="col"><label class="form-label small">District</label><input name="buyer_district" class="form-control" value="{{ $value('buyer_district') }}"></div>
                        <div class="col"><label class="form-label small">City</label><input name="buyer_city" class="form-control" value="{{ $value('buyer_city') }}"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <span class="z-chip"><i class="bi bi-basket"></i></span> Items
                    <span class="z-status warn ms-1"><i class="bi bi-percent"></i> VAT 15% standard rate</span>
                    <span class="ar">البنود</span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle" id="items">
                        <thead>
                        <tr>
                            <th>Description <span class="ar">الوصف</span></th>
                            <th style="width:110px">Qty <span class="ar">الكمية</span></th>
                            <th style="width:140px">Unit price <span class="ar">سعر الوحدة</span></th>
                            <th class="text-end" style="width:130px">Total incl. VAT <span class="ar">الإجمالي</span></th>
                            <th style="width:50px"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($items as $i => $item)
                            <tr>
                                <td data-label="Description"><input name="items[{{ $i }}][name]" class="form-control" value="{{ $item['name'] }}" required></td>
                                <td data-label="Qty"><input name="items[{{ $i }}][quantity]" type="number" step="any" min="0" class="form-control qty" value="{{ $item['quantity'] }}" required></td>
                                <td data-label="Unit price"><input name="items[{{ $i }}][unit_price]" type="number" step="0.01" min="0" class="form-control price" value="{{ $item['unit_price'] }}" required></td>
                                <td class="text-end fw-semibold line-total" data-label="Total incl. VAT">0.00</td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger remove" title="Remove item"><i class="bi bi-trash3"></i></button></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <button type="button" class="btn btn-outline-secondary" id="add-item"><i class="bi bi-plus-circle"></i> Add item</button>
                        </div>
                        <div class="col-md-7">
                            <div class="z-totals">
                                <div class="d-flex justify-content-between gap-2"><span>Total excl. VAT <span class="ar small">الإجمالي غير شامل الضريبة</span></span><span id="sum-net">0.00</span></div>
                                <div class="d-flex justify-content-between gap-2"><span>VAT 15% <span class="ar small">ضريبة القيمة المضافة</span></span><span id="sum-vat">0.00</span></div>
                                <div class="d-flex justify-content-between gap-2 z-totals-grand"><span>Total incl. VAT (SAR) <span class="ar small">الإجمالي شامل الضريبة</span></span><span id="sum-total">0.00</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="submit_now" value="1" id="submit_now" checked>
                        <label class="form-check-label" for="submit_now"><i class="bi bi-cloud-arrow-up"></i> Submit to ZATCA immediately</label>
                    </div>
                    <button class="btn btn-success px-4"><i class="bi bi-pen"></i> Sign &amp; save <span class="ar small ms-1">توقيع وحفظ</span></button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    (function () {
        const tbody = document.querySelector('#items tbody');
        const round = (n) => Math.round((n + Number.EPSILON) * 100) / 100;
        let index = tbody.rows.length;

        function recalc() {
            let net = 0;
            tbody.querySelectorAll('tr').forEach((row) => {
                const line = round((parseFloat(row.querySelector('.qty').value) || 0) * (parseFloat(row.querySelector('.price').value) || 0));
                row.querySelector('.line-total').textContent = round(line + round(line * 0.15)).toFixed(2);
                net += line;
            });
            const vat = round(round(net) * 0.15);
            document.getElementById('sum-net').textContent = round(net).toFixed(2);
            document.getElementById('sum-vat').textContent = vat.toFixed(2);
            document.getElementById('sum-total').textContent = round(net + vat).toFixed(2);
        }

        document.getElementById('add-item').addEventListener('click', () => {
            const row = tbody.rows[0].cloneNode(true);
            row.querySelectorAll('input').forEach((input) => {
                input.name = input.name.replace(/items\[\d+]/, `items[${index}]`);
                input.value = input.classList.contains('qty') ? 1 : '';
            });
            tbody.appendChild(row);
            index++;
            recalc();
        });

        tbody.addEventListener('click', (e) => {
            const button = e.target.closest('.remove');
            if (button && tbody.rows.length > 1) {
                button.closest('tr').remove();
                recalc();
            }
        });
        tbody.addEventListener('input', recalc);

        function toggleFields() {
            const isNote = document.getElementById('document_type').value !== 'invoice';
            const isStandard = document.getElementById('invoice_type').value === 'standard';
            document.querySelector('.note-fields').style.display = isNote ? '' : 'none';
            document.querySelectorAll('.note-fields input').forEach((input) => input.required = isNote);
            document.querySelector('.buyer-optional').style.display = isStandard ? 'none' : '';
            document.querySelectorAll('[name^="buyer_"]').forEach((input) => {
                input.required = isStandard && input.name !== 'buyer_vat_number';
            });
        }

        document.getElementById('document_type').addEventListener('change', toggleFields);
        document.getElementById('invoice_type').addEventListener('change', toggleFields);
        toggleFields();
        recalc();
    })();
</script>
@endpush
