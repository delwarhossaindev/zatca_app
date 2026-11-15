<?php

namespace App\Services\Zatca;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for the ZATCA Fatoora e-invoicing APIs.
 */
class ZatcaApi
{
    public function __construct(private string $environment)
    {
    }

    /**
     * Step 1: request a Compliance CSID with the CSR and the OTP from the Fatoora portal.
     */
    public function complianceCsid(string $csr, string $otp): Response
    {
        return $this->request()
            ->withHeaders(['OTP' => $otp])
            ->post('/compliance', ['csr' => base64_encode($csr)]);
    }

    /**
     * Step 2: validate sample invoices against the compliance CSID.
     */
    public function complianceInvoice(string $certificate, string $secret, array $payload): Response
    {
        return $this->request()
            ->withBasicAuth($certificate, $secret)
            ->post('/compliance/invoices', $payload);
    }

    /**
     * Step 3: exchange the compliance CSID for a Production CSID.
     */
    public function productionCsid(string $certificate, string $secret, string $complianceRequestId): Response
    {
        return $this->request()
            ->withBasicAuth($certificate, $secret)
            ->post('/production/csids', ['compliance_request_id' => $complianceRequestId]);
    }

    /**
     * Report a simplified (B2C) invoice within 24 hours.
     */
    public function report(string $certificate, string $secret, array $payload): Response
    {
        return $this->request()
            ->withBasicAuth($certificate, $secret)
            ->withHeaders(['Clearance-Status' => '0'])
            ->post('/invoices/reporting/single', $payload);
    }

    /**
     * Clear a standard (B2B) invoice before sharing it with the buyer.
     */
    public function clear(string $certificate, string $secret, array $payload): Response
    {
        return $this->request()
            ->withBasicAuth($certificate, $secret)
            ->withHeaders(['Clearance-Status' => '1'])
            ->post('/invoices/clearance/single', $payload);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(config("zatca.environments.{$this->environment}.url"))
            ->timeout(config('zatca.timeout'))
            ->acceptJson()
            ->withHeaders([
                'Accept-Language' => 'en',
                'Accept-Version' => 'V2',
            ]);
    }
}
