# ZATCA E-Invoicing (Phase 1 & 2) — Laravel

Laravel 10 app that onboards an EGS unit with ZATCA (Fatoora) and issues signed,
reported and cleared e-invoices. Implemented natively in PHP (OpenSSL + DOM), no Java SDK needed.

## Setup

```bash
composer install
cp .env.example .env        # DB_CONNECTION=sqlite, ZATCA_ENV=sandbox
php artisan key:generate
php artisan migrate         # uses database/database.sqlite
php artisan serve
```

Open http://127.0.0.1:8000 (or http://localhost/zatca_app/public under WAMP).

## Onboarding (ZATCA Onboarding page)

1. **Seller & EGS details** — VAT number (15 digits, starts/ends with 3), CRN, address, EGS name/serial.
2. **Generate CSR** — secp256k1 key + CSR with the ZATCA certificate template
   (`TSTZATCA-Code-Signing` / `PREZATCA-Code-Signing` / `ZATCA-Code-Signing`).
3. **Compliance CSID** — `POST /compliance` with the OTP (sandbox OTP: `123345`;
   simulation/production: generate it on the Fatoora portal).
4. **Compliance checks** — signs and submits standard/simplified invoice, credit and debit note to
   `POST /compliance/invoices`.
5. **Production CSID** — `POST /production/csids`.

## Invoices

- **Simplified (B2C)** → `POST /invoices/reporting/single` (report within 24 hours), QR tags 1–9.
- **Standard (B2B)** → `POST /invoices/clearance/single`; the cleared XML (with ZATCA's QR) is stored.
- Credit (381) and debit (383) notes reference the original invoice and require a reason.
- Every invoice gets an ICV counter and the previous invoice hash (PIH) to form the hash chain.

## Code

| File | Purpose |
| --- | --- |
| `app/Services/Zatca/CsrGenerator.php` | Private key + CSR |
| `app/Services/Zatca/ZatcaApi.php` | Fatoora API client |
| `app/Services/Zatca/InvoiceXmlBuilder.php` | UBL 2.1 XML |
| `app/Services/Zatca/InvoiceSigner.php` | Invoice hash, ECDSA signature, XAdES signed properties, QR |
| `app/Services/Zatca/Certificate.php` | CSID certificate helpers (hash, issuer, serial, public key, signature) |
| `app/Services/Zatca/QrCode.php` | TLV QR encoding |
| `app/Services/Zatca/ZatcaService.php` | Onboarding flow, signing, reporting/clearance |
| `config/zatca.php` | Environment URLs and certificate templates |

## Postman collection

Import `ZATCA-API.postman_collection.json` into Postman. CSID tokens are saved to collection variables automatically.
Get values from the app with `php artisan zatca:payload` (CSR and credentials) or `php artisan zatca:payload INV-000001` (invoice payload).

## Environments

| `environment` | URL |
| --- | --- |
| sandbox | `https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal` |
| simulation | `https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation` |
| production | `https://gw-fatoora.zatca.gov.sa/e-invoicing/core` |

Changing the environment requires generating a new CSR and onboarding again.
