@extends('layouts.app')

@section('title', 'New invoice')

@php
    $items = old('items', $original
        ? $original->items->map->only(['name', 'quantity', 'unit_price'])->all()
        : [['name' => '', 'quantity' => 1, 'unit_price' => '']]);
    $value = fn ($field, $default = null) => old($field, $original?->{$field} ?? $default);
@endphp

@section('content')
<h1 class="h3 mb-3">New {{ $documentType === 'invoice' ? 'invoice' : $documentType.' note' }}</h1>

<form method="POST" action="{{ route('invoices.store') }}" id="invoice-form">
    @csrf
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header bg-white fw-semibold">Document</div>
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
                <div class="card-header bg-white fw-semibold">Buyer <span class="text-muted small buyer-optional">(optional for simplified)</span></div>
                <div class="card-body">
                    <div class="mb-2"><label class="form-label small">Name</label><input name="buyer_name" class="form-control" value="{{ $value('buyer_name') }}"></div>
                    <div class="mb-2"><label class="form-label small">VAT number</label><input name="buyer_vat_number" class="form-control" maxlength="15" value="{{ $value('buyer_vat_number') }}"></div>
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
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    Items <span class="badge bg-light text-dark">VAT 15% (Standard rate)</span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="items">
                        <thead class="table-light">
                        <tr><th>Description</th><th style="width:110px">Qty</th><th style="width:140px">Unit price</th><th class="text-end" style="width:130px">Total incl. VAT</th><th style="width:50px"></th></tr>
                        </thead>
                        <tbody>
                        @foreach ($items as $i => $item)
                            <tr>
                                <td><input name="items[{{ $i }}][name]" class="form-control" value="{{ $item['name'] }}" required></td>
                                <td><input name="items[{{ $i }}][quantity]" type="number" step="any" min="0" class="form-control qty" value="{{ $item['quantity'] }}" required></td>
                                <td><input name="items[{{ $i }}][unit_price]" type="number" step="0.01" min="0" class="form-control price" value="{{ $item['unit_price'] }}" required></td>
                                <td class="text-end line-total">0.00</td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger remove"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="add-item"><i class="bi bi-plus"></i> Add item</button>
                    <dl class="row mb-0 mt-3 text-end">
                        <dt class="col-8 fw-normal">Total excl. VAT</dt><dd class="col-4" id="sum-net">0.00</dd>
                        <dt class="col-8 fw-normal">VAT 15%</dt><dd class="col-4" id="sum-vat">0.00</dd>
                        <dt class="col-8">Total incl. VAT (SAR)</dt><dd class="col-4 fw-bold" id="sum-total">0.00</dd>
                    </dl>
                </div>
                <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="submit_now" value="1" id="submit_now" checked>
                        <label class="form-check-label" for="submit_now">Submit to ZATCA immediately</label>
                    </div>
                    <button class="btn btn-success"><i class="bi bi-pen"></i> Sign & save</button>
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
