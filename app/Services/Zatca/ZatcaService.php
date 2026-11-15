<?php

namespace App\Services\Zatca;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ZatcaSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Orchestrates ZATCA onboarding (CSR -> Compliance CSID -> compliance checks -> Production CSID)
 * and invoice signing, reporting and clearance.
 */
class ZatcaService
{
    public function __construct(
        private CsrGenerator $csrGenerator,
        private InvoiceXmlBuilder $xmlBuilder,
        private InvoiceSigner $signer,
    ) {
    }

    public function api(ZatcaSetting $setting): ZatcaApi
    {
        return new ZatcaApi($setting->environment);
    }

    public function generateCsr(ZatcaSetting $setting): void
    {
        $result = $this->csrGenerator->generate($setting);

        $setting->update([
            'private_key' => $result['private_key'],
            'csr' => $result['csr'],
            'compliance_request_id' => null,
            'compliance_certificate' => null,
            'compliance_secret' => null,
            'compliance_checks' => null,
            'production_request_id' => null,
            'production_certificate' => null,
            'production_secret' => null,
        ]);
    }

    public function requestComplianceCsid(ZatcaSetting $setting, string $otp): void
    {
        $response = $this->api($setting)->complianceCsid($setting->csr, $otp);
        $this->ensureSuccessful($response, 'Compliance CSID');

        $setting->update([
            'compliance_request_id' => $response->json('requestID'),
            'compliance_certificate' => $response->json('binarySecurityToken'),
            'compliance_secret' => $response->json('secret'),
            'compliance_checks' => null,
        ]);
    }

    /**
     * Sign and submit one sample of every document type the EGS unit is onboarded for.
     *
     * @return array<string, array{status: string, http: int, errors: array, warnings: array}>
     */
    public function runComplianceChecks(ZatcaSetting $setting): array
    {
        $certificate = new Certificate(base64_decode($setting->compliance_certificate));
        $previousHash = ZatcaSetting::INITIAL_PIH;
        $results = [];

        foreach ($this->complianceDocumentTypes($setting) as $index => [$invoiceType, $documentType]) {
            $invoice = $this->sampleInvoice($invoiceType, $documentType, $index + 1, $previousHash);
            $signed = $this->sign($invoice, $setting, $certificate);

            $response = $this->api($setting)->complianceInvoice(
                $setting->compliance_certificate,
                $setting->compliance_secret,
                $this->payload($invoice->uuid, $signed['hash'], $signed['xml']),
            );

            $results[$invoice->documentLabel()] = $this->summarize($response);
            $previousHash = $signed['hash'];
        }

        $setting->update(['compliance_checks' => $results]);

        return $results;
    }

    public function requestProductionCsid(ZatcaSetting $setting): void
    {
        $response = $this->api($setting)->productionCsid(
            $setting->compliance_certificate,
            $setting->compliance_secret,
            $setting->compliance_request_id,
        );
        $this->ensureSuccessful($response, 'Production CSID');

        $setting->update([
            'production_request_id' => $response->json('requestID'),
            'production_certificate' => $response->json('binarySecurityToken'),
            'production_secret' => $response->json('secret'),
        ]);
    }

    /**
     * Assign ICV/PIH from the invoice chain, then sign the invoice and store XML, hash and QR.
     */
    public function prepare(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $setting = ZatcaSetting::query()->lockForUpdate()->firstOrFail();

            if (! $setting->signingCertificate()) {
                throw new RuntimeException('Complete ZATCA onboarding before issuing invoices.');
            }

            $invoice->icv = $setting->icv + 1;
            $invoice->previous_invoice_hash = $setting->previousInvoiceHash();

            $signed = $this->sign($invoice, $setting, new Certificate($setting->signingCertificate()));

            $invoice->forceFill([
                'invoice_hash' => $signed['hash'],
                'signed_xml' => $signed['xml'],
                'qr_code' => $signed['qr'],
            ])->save();

            $setting->update([
                'icv' => $invoice->icv,
                'previous_invoice_hash' => $signed['hash'],
            ]);
        });
    }

    /**
     * Report (simplified) or clear (standard) an invoice with the Production CSID.
     */
    public function submit(Invoice $invoice): array
    {
        $setting = ZatcaSetting::current();

        if (! $setting?->hasProductionCsid()) {
            throw new RuntimeException('A Production CSID is required to report or clear invoices.');
        }

        $api = $this->api($setting);
        $payload = $this->payload($invoice->uuid, $invoice->invoice_hash, $invoice->signed_xml);

        $response = $invoice->isSimplified()
            ? $api->report($setting->production_certificate, $setting->production_secret, $payload)
            : $api->clear($setting->production_certificate, $setting->production_secret, $payload);

        $summary = $this->summarize($response);
        $cleared = $response->json('clearedInvoice');

        if ($cleared) {
            // The cleared invoice is stamped by ZATCA and carries ZATCA's QR code
            $invoice->cleared_xml = base64_decode($cleared);
            $invoice->qr_code = $this->extractQr($invoice->cleared_xml) ?? $invoice->qr_code;
        }

        $invoice->fill([
            'zatca_status' => $summary['status'],
            'zatca_response' => $response->json() ?? ['body' => $response->body()],
            'submitted_at' => now(),
        ])->save();

        return $summary;
    }

    /**
     * @return array{xml: string, hash: string, signature: string, qr: string}
     */
    private function sign(Invoice $invoice, ZatcaSetting $setting, Certificate $certificate): array
    {
        $xml = $this->xmlBuilder->build($invoice, $setting);

        return $this->signer->sign($xml, $setting->private_key, $certificate, [
            'seller_name' => $setting->seller_name,
            'vat_number' => $setting->vat_number,
            'timestamp' => $invoice->issueTimestamp(),
            'total' => number_format((float) $invoice->total_amount, 2, '.', ''),
            'vat_total' => number_format((float) $invoice->vat_amount, 2, '.', ''),
            'simplified' => $invoice->isSimplified(),
        ]);
    }

    private function extractQr(string $xml): ?string
    {
        $pattern = '#<cbc:ID>QR</cbc:ID>\s*<cac:Attachment>\s*<cbc:EmbeddedDocumentBinaryObject[^>]*>([^<]+)<#';

        return preg_match($pattern, $xml, $matches) ? trim($matches[1]) : null;
    }

    private function payload(string $uuid, string $hash, string $xml): array
    {
        return [
            'invoiceHash' => $hash,
            'uuid' => $uuid,
            'invoice' => base64_encode($xml),
        ];
    }

    /**
     * @return array{status: string, http: int, errors: array, warnings: array}
     */
    private function summarize(Response $response): array
    {
        $json = $response->json() ?? [];
        $results = $json['validationResults'] ?? [];

        $status = $json['reportingStatus']
            ?? $json['clearanceStatus']
            ?? ($results['status'] ?? null)
            ?? ($response->successful() ? 'OK' : 'ERROR');

        $messages = fn ($items) => collect($items ?? [])
            ->map(fn ($item) => trim(($item['code'] ?? '').': '.($item['message'] ?? ''), ': '))
            ->values()
            ->all();

        $errors = $messages($results['errorMessages'] ?? []);

        if (! $response->successful() && empty($errors)) {
            $errors = $messages($json['errors'] ?? []) ?: [$json['message'] ?? $response->body() ?: 'HTTP '.$response->status()];
        }

        return [
            'status' => $status,
            'http' => $response->status(),
            'errors' => $errors,
            'warnings' => $messages($results['warningMessages'] ?? []),
        ];
    }

    private function ensureSuccessful(Response $response, string $step): void
    {
        if ($response->successful()) {
            return;
        }

        $json = $response->json() ?? [];
        $message = collect($json['errors'] ?? [])->map(fn ($e) => is_array($e) ? ($e['message'] ?? json_encode($e)) : $e)->implode('; ')
            ?: ($json['message'] ?? $json['errorMessage'] ?? $response->body());

        throw new RuntimeException("$step request failed (HTTP {$response->status()}): $message");
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function complianceDocumentTypes(ZatcaSetting $setting): array
    {
        $types = [];

        foreach ([Invoice::TYPE_STANDARD => 0, Invoice::TYPE_SIMPLIFIED => 1] as $invoiceType => $position) {
            if (($setting->invoice_types[$position] ?? '0') === '1') {
                foreach (['invoice', 'credit', 'debit'] as $documentType) {
                    $types[] = [$invoiceType, $documentType];
                }
            }
        }

        return $types;
    }

    private function sampleInvoice(string $invoiceType, string $documentType, int $icv, string $previousHash): Invoice
    {
        $invoice = new Invoice([
            'uuid' => (string) Str::uuid(),
            'invoice_number' => 'COMPLIANCE-'.$icv,
            'invoice_type' => $invoiceType,
            'document_type' => $documentType,
            'issue_date' => now(),
            'issue_time' => now()->format('H:i:s'),
            'original_invoice_number' => $documentType === 'invoice' ? null : 'COMPLIANCE-1',
            'note_reason' => $documentType === 'invoice' ? null : 'Returned items',
            'payment_means' => '10',
            'currency' => 'SAR',
            'buyer_name' => 'Fatoora Samples LTD',
            'buyer_vat_number' => '399999999800003',
            'buyer_street' => 'Salah Al-Din',
            'buyer_building_number' => '1111',
            'buyer_district' => 'Al-Murooj',
            'buyer_city' => 'Riyadh',
            'buyer_postal_code' => '12222',
            'icv' => $icv,
            'previous_invoice_hash' => $previousHash,
        ]);

        $items = collect([
            new InvoiceItem(['name' => 'Sample item', 'quantity' => 2, 'unit_price' => 50, 'vat_category' => 'S', 'vat_percent' => 15]),
        ]);

        $invoice->calculateTotals($items);
        $invoice->setRelation('items', $items);

        return $invoice;
    }
}
