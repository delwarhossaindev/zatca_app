<?php

namespace App\Services\Zatca;

use App\Models\ZatcaSetting;
use RuntimeException;

/**
 * Generates the secp256k1 private key and the CSR with ZATCA specific extensions.
 */
class CsrGenerator
{
    /**
     * @return array{private_key: string, csr: string}
     */
    public function generate(ZatcaSetting $setting): array
    {
        $template = config("zatca.environments.{$setting->environment}.certificate_template");
        $configPath = $this->writeConfig($setting, $template);

        try {
            $options = ['config' => $configPath, 'digest_alg' => 'sha256', 'req_extensions' => 'req_ext'];

            $key = openssl_pkey_new($options + [
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'curve_name' => 'secp256k1',
            ]);

            if ($key === false) {
                throw new RuntimeException('Unable to generate private key: '.$this->opensslErrors());
            }

            $dn = [
                'countryName' => 'SA',
                'organizationalUnitName' => $setting->branch_name,
                'organizationName' => $setting->seller_name,
                'commonName' => $setting->egs_name,
            ];

            $csr = openssl_csr_new($dn, $key, $options);

            if ($csr === false) {
                throw new RuntimeException('Unable to generate CSR: '.$this->opensslErrors());
            }

            openssl_csr_export($csr, $csrPem);
            openssl_pkey_export($key, $privateKeyPem, null, $options);
        } finally {
            @unlink($configPath);
        }

        return ['private_key' => $privateKeyPem, 'csr' => $csrPem];
    }

    private function writeConfig(ZatcaSetting $setting, string $template): string
    {
        $config = <<<CNF
        oid_section = OIDs

        [ OIDs ]
        certificateTemplateName = 1.3.6.1.4.1.311.20.2

        [ req ]
        prompt = no
        default_bits = 2048
        utf8 = yes
        default_md = sha256
        distinguished_name = dn
        req_extensions = req_ext

        [ dn ]

        [ req_ext ]
        certificateTemplateName = ASN1:PRINTABLESTRING:{$template}
        subjectAltName = dirName:alt_names

        [ alt_names ]
        SN = {$this->value($setting->egs_serial)}
        UID = {$this->value($setting->vat_number)}
        title = {$this->value($setting->invoice_types)}
        registeredAddress = {$this->value($setting->street.' '.$setting->city)}
        businessCategory = {$this->value($setting->industry)}

        CNF;

        $path = tempnam(sys_get_temp_dir(), 'zatca_csr_');
        file_put_contents($path, $config);

        return $path;
    }

    private function value(string $value): string
    {
        // Keep values on a single line and escape characters with special meaning in openssl.cnf
        return addcslashes(preg_replace('/[\r\n]+/', ' ', trim($value)), '"#$\\;');
    }

    private function opensslErrors(): string
    {
        $errors = [];

        while ($error = openssl_error_string()) {
            $errors[] = $error;
        }

        return implode('; ', $errors);
    }
}
