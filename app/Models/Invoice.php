<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const TYPE_STANDARD = 'standard';
    public const TYPE_SIMPLIFIED = 'simplified';

    public const DOCUMENT_CODES = [
        'invoice' => '388',
        'debit' => '383',
        'credit' => '381',
    ];

    protected $guarded = ['id'];

    protected $attributes = [
        'currency' => 'SAR',
        'payment_means' => '10',
        'zatca_status' => 'pending',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'zatca_response' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function isSimplified(): bool
    {
        return $this->invoice_type === self::TYPE_SIMPLIFIED;
    }

    public function isNote(): bool
    {
        return $this->document_type !== 'invoice';
    }

    public function documentCode(): string
    {
        return self::DOCUMENT_CODES[$this->document_type];
    }

    /**
     * KSA-2 invoice type transaction code (NNPNESB).
     */
    public function transactionCode(): string
    {
        return $this->isSimplified() ? '0200000' : '0100000';
    }

    public function issueTimestamp(): string
    {
        return $this->issue_date->format('Y-m-d').'T'.$this->issue_time;
    }

    public function documentLabel(): string
    {
        $type = $this->isSimplified() ? 'Simplified' : 'Standard';

        return match ($this->document_type) {
            'credit' => "$type Credit Note",
            'debit' => "$type Debit Note",
            default => "$type Tax Invoice",
        };
    }

    /**
     * Recalculate line and document totals from the items.
     */
    public function calculateTotals(iterable $items): void
    {
        $lineTotal = 0;

        foreach ($items as $item) {
            $item->line_extension_amount = round($item->quantity * $item->unit_price, 2);
            $item->vat_amount = round($item->line_extension_amount * $item->vat_percent / 100, 2);
            $lineTotal += $item->line_extension_amount;
        }

        // Document VAT is calculated per category on the taxable amount (BR-S-09)
        $vatTotal = collect($this->taxSubtotals($items))->sum('tax');

        $this->line_extension_amount = round($lineTotal, 2);
        $this->vat_amount = round($vatTotal, 2);
        $this->total_amount = round($lineTotal + $vatTotal, 2);
    }

    /**
     * Taxable and tax amounts grouped by VAT category and rate.
     *
     * @return array<int, array{category: string, percent: float, taxable: float, tax: float}>
     */
    public function taxSubtotals(?iterable $items = null): array
    {
        $groups = [];

        foreach ($items ?? $this->items as $item) {
            $key = $item->vat_category.'|'.$item->vat_percent;
            $groups[$key] ??= ['category' => $item->vat_category, 'percent' => (float) $item->vat_percent, 'taxable' => 0];
            $groups[$key]['taxable'] += $item->line_extension_amount;
        }

        return array_values(array_map(function ($group) {
            $group['taxable'] = round($group['taxable'], 2);
            $group['tax'] = round($group['taxable'] * $group['percent'] / 100, 2);

            return $group;
        }, $groups));
    }
}
