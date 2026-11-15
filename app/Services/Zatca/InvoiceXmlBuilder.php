<?php

namespace App\Services\Zatca;

use App\Models\Invoice;
use App\Models\ZatcaSetting;

/**
 * Builds the UBL 2.1 invoice XML in the format ZATCA expects (4 space indentation).
 *
 * The UBL extensions (signature), the QR reference and cac:Signature are written
 * with placeholders that the InvoiceSigner fills in. Those three nodes are excluded
 * from the invoice hash, so the hash is identical before and after signing.
 */
class InvoiceXmlBuilder
{
    public const PLACEHOLDER_UBL_EXTENSIONS = '{{UBL_EXTENSIONS}}';
    public const PLACEHOLDER_QR = '{{QR_CODE}}';

    private const INDENT = '    ';

    public function build(Invoice $invoice, ZatcaSetting $seller): string
    {
        $children = array_filter([
            ['cbc:ProfileID', 'reporting:1.0'],
            ['cbc:ID', $invoice->invoice_number],
            ['cbc:UUID', $invoice->uuid],
            ['cbc:IssueDate', $invoice->issue_date->format('Y-m-d')],
            ['cbc:IssueTime', $invoice->issue_time],
            ['cbc:InvoiceTypeCode', $invoice->documentCode(), ['name' => $invoice->transactionCode()]],
            ['cbc:DocumentCurrencyCode', $invoice->currency],
            ['cbc:TaxCurrencyCode', 'SAR'],
            $invoice->isNote() ? ['cac:BillingReference', [
                ['cac:InvoiceDocumentReference', [
                    ['cbc:ID', $invoice->original_invoice_number],
                ]],
            ]] : null,
            ['cac:AdditionalDocumentReference', [
                ['cbc:ID', 'ICV'],
                ['cbc:UUID', (string) $invoice->icv],
            ]],
            ['cac:AdditionalDocumentReference', [
                ['cbc:ID', 'PIH'],
                ['cac:Attachment', [
                    ['cbc:EmbeddedDocumentBinaryObject', $invoice->previous_invoice_hash, ['mimeCode' => 'text/plain']],
                ]],
            ]],
            ['cac:AdditionalDocumentReference', [
                ['cbc:ID', 'QR'],
                ['cac:Attachment', [
                    ['cbc:EmbeddedDocumentBinaryObject', self::PLACEHOLDER_QR, ['mimeCode' => 'text/plain']],
                ]],
            ]],
            ['cac:Signature', [
                ['cbc:ID', 'urn:oasis:names:specification:ubl:signature:Invoice'],
                ['cbc:SignatureMethod', 'urn:oasis:names:specification:ubl:dsig:enveloped:xades'],
            ]],
            $this->supplier($seller),
            $this->customer($invoice),
            $invoice->isSimplified() ? null : ['cac:Delivery', [
                ['cbc:ActualDeliveryDate', $invoice->issue_date->format('Y-m-d')],
            ]],
            ['cac:PaymentMeans', array_filter([
                ['cbc:PaymentMeansCode', $invoice->payment_means],
                $invoice->isNote() ? ['cbc:InstructionNote', $invoice->note_reason] : null,
            ])],
            ...$this->taxTotals($invoice),
            $this->monetaryTotal($invoice),
            ...$this->lines($invoice),
        ]);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"'
            .' xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"'
            .' xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"'
            .' xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">'."\n";
        $xml .= self::PLACEHOLDER_UBL_EXTENSIONS."\n";

        foreach ($children as $child) {
            $xml .= $this->render($child, 1);
        }

        return $xml.'</Invoice>';
    }

    private function supplier(ZatcaSetting $seller): array
    {
        return ['cac:AccountingSupplierParty', [
            ['cac:Party', array_filter([
                $seller->crn ? ['cac:PartyIdentification', [
                    ['cbc:ID', $seller->crn, ['schemeID' => 'CRN']],
                ]] : null,
                $this->address([
                    'street' => $seller->street,
                    'building' => $seller->building_number,
                    'plot' => $seller->plot_identification,
                    'district' => $seller->district,
                    'city' => $seller->city,
                    'postal' => $seller->postal_code,
                ]),
                ['cac:PartyTaxScheme', [
                    ['cbc:CompanyID', $seller->vat_number],
                    ['cac:TaxScheme', [['cbc:ID', 'VAT']]],
                ]],
                ['cac:PartyLegalEntity', [
                    ['cbc:RegistrationName', $seller->seller_name],
                ]],
            ])],
        ]];
    }

    private function customer(Invoice $invoice): array
    {
        if (blank($invoice->buyer_name) && $invoice->isSimplified()) {
            return ['cac:AccountingCustomerParty', [['cac:Party', [
                ['cac:PartyLegalEntity', [['cbc:RegistrationName', 'Cash Customer']]],
            ]]]];
        }

        return ['cac:AccountingCustomerParty', [
            ['cac:Party', array_filter([
                filled($invoice->buyer_street) ? $this->address([
                    'street' => $invoice->buyer_street,
                    'building' => $invoice->buyer_building_number,
                    'district' => $invoice->buyer_district,
                    'city' => $invoice->buyer_city,
                    'postal' => $invoice->buyer_postal_code,
                ]) : null,
                filled($invoice->buyer_vat_number) ? ['cac:PartyTaxScheme', [
                    ['cbc:CompanyID', $invoice->buyer_vat_number],
                    ['cac:TaxScheme', [['cbc:ID', 'VAT']]],
                ]] : null,
                ['cac:PartyLegalEntity', [
                    ['cbc:RegistrationName', $invoice->buyer_name],
                ]],
            ])],
        ]];
    }

    private function address(array $address): array
    {
        return ['cac:PostalAddress', array_filter([
            ['cbc:StreetName', $address['street']],
            ['cbc:BuildingNumber', $address['building']],
            filled($address['plot'] ?? null) ? ['cbc:PlotIdentification', $address['plot']] : null,
            ['cbc:CitySubdivisionName', $address['district']],
            ['cbc:CityName', $address['city']],
            ['cbc:PostalZone', $address['postal']],
            ['cac:Country', [['cbc:IdentificationCode', 'SA']]],
        ])];
    }

    private function taxTotals(Invoice $invoice): array
    {
        $currency = ['currencyID' => $invoice->currency];
        $subtotals = [];

        foreach ($invoice->taxSubtotals() as $subtotal) {
            $subtotals[] = ['cac:TaxSubtotal', [
                ['cbc:TaxableAmount', $this->amount($subtotal['taxable']), $currency],
                ['cbc:TaxAmount', $this->amount($subtotal['tax']), $currency],
                ['cac:TaxCategory', [
                    ['cbc:ID', $subtotal['category'], ['schemeID' => 'UN/ECE 5305', 'schemeAgencyID' => '6']],
                    ['cbc:Percent', $this->amount($subtotal['percent'])],
                    ['cac:TaxScheme', [
                        ['cbc:ID', 'VAT', ['schemeID' => 'UN/ECE 5153', 'schemeAgencyID' => '6']],
                    ]],
                ]],
            ]];
        }

        // The first TaxTotal is in the tax currency (SAR), the second carries the breakdown
        return [
            ['cac:TaxTotal', [
                ['cbc:TaxAmount', $this->amount($invoice->vat_amount), ['currencyID' => 'SAR']],
            ]],
            ['cac:TaxTotal', [
                ['cbc:TaxAmount', $this->amount($invoice->vat_amount), $currency],
                ...$subtotals,
            ]],
        ];
    }

    private function monetaryTotal(Invoice $invoice): array
    {
        $currency = ['currencyID' => $invoice->currency];

        return ['cac:LegalMonetaryTotal', [
            ['cbc:LineExtensionAmount', $this->amount($invoice->line_extension_amount), $currency],
            ['cbc:TaxExclusiveAmount', $this->amount($invoice->line_extension_amount), $currency],
            ['cbc:TaxInclusiveAmount', $this->amount($invoice->total_amount), $currency],
            ['cbc:AllowanceTotalAmount', '0.00', $currency],
            ['cbc:PrepaidAmount', '0.00', $currency],
            ['cbc:PayableAmount', $this->amount($invoice->total_amount), $currency],
        ]];
    }

    private function lines(Invoice $invoice): array
    {
        $currency = ['currencyID' => $invoice->currency];
        $lines = [];

        foreach ($invoice->items->values() as $index => $item) {
            $lines[] = ['cac:InvoiceLine', [
                ['cbc:ID', (string) ($index + 1)],
                ['cbc:InvoicedQuantity', number_format((float) $item->quantity, 6, '.', ''), ['unitCode' => 'PCE']],
                ['cbc:LineExtensionAmount', $this->amount($item->line_extension_amount), $currency],
                ['cac:TaxTotal', [
                    ['cbc:TaxAmount', $this->amount($item->vat_amount), $currency],
                    ['cbc:RoundingAmount', $this->amount($item->line_extension_amount + $item->vat_amount), $currency],
                ]],
                ['cac:Item', [
                    ['cbc:Name', $item->name],
                    ['cac:ClassifiedTaxCategory', [
                        ['cbc:ID', $item->vat_category],
                        ['cbc:Percent', $this->amount($item->vat_percent)],
                        ['cac:TaxScheme', [['cbc:ID', 'VAT']]],
                    ]],
                ]],
                ['cac:Price', [
                    ['cbc:PriceAmount', $this->amount($item->unit_price), $currency],
                ]],
            ]];
        }

        return $lines;
    }

    /**
     * Render a [name, value|children, attributes] node.
     */
    private function render(array $node, int $level): string
    {
        [$name, $content] = $node;
        $attributes = '';

        foreach ($node[2] ?? [] as $key => $value) {
            $attributes .= ' '.$key.'="'.$this->escape($value).'"';
        }

        $indent = str_repeat(self::INDENT, $level);

        if (! is_array($content)) {
            return "$indent<$name$attributes>".$this->escape((string) $content)."</$name>\n";
        }

        $xml = "$indent<$name$attributes>\n";

        foreach ($content as $child) {
            $xml .= $this->render($child, $level + 1);
        }

        return $xml."$indent</$name>\n";
    }

    private function amount(float|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
