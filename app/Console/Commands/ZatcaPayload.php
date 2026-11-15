<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\ZatcaSetting;
use Illuminate\Console\Command;

class ZatcaPayload extends Command
{
    protected $signature = 'zatca:payload
                            {invoice? : Invoice ID or number (omit to print the CSR and credentials)}';

    protected $description = 'Print ZATCA API request values for use in Postman';

    public function handle(): int
    {
        $setting = ZatcaSetting::current();

        if (! $this->argument('invoice')) {
            $this->line(json_encode(array_filter([
                'csr' => $setting?->csr ? base64_encode($setting->csr) : null,
                'complianceToken' => $setting?->compliance_certificate,
                'complianceSecret' => $setting?->compliance_secret,
                'complianceRequestId' => $setting?->compliance_request_id,
                'productionToken' => $setting?->production_certificate,
                'productionSecret' => $setting?->production_secret,
            ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $invoice = Invoice::query()
            ->where('id', $this->argument('invoice'))
            ->orWhere('invoice_number', $this->argument('invoice'))
            ->first();

        if (! $invoice?->signed_xml) {
            $this->error('Signed invoice not found.');

            return self::FAILURE;
        }

        $this->line(json_encode([
            'invoiceHash' => $invoice->invoice_hash,
            'uuid' => $invoice->uuid,
            'invoice' => base64_encode($invoice->signed_xml),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
