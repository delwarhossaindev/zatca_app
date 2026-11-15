<?php

namespace App\Http\Controllers;

use App\Models\ZatcaSetting;
use App\Services\Zatca\Certificate;
use App\Services\Zatca\ZatcaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ZatcaSettingController extends Controller
{
    public function __construct(private ZatcaService $zatca)
    {
    }

    public function edit(): View
    {
        $setting = ZatcaSetting::current() ?? new ZatcaSetting([
            'environment' => config('zatca.env'),
            'egs_name' => 'EGS1-'.Str::upper(Str::random(6)),
            'egs_model' => '1',
            'egs_serial' => '1-'.Str::slug(config('app.name')).'|2-1.0|3-'.Str::uuid(),
            'invoice_types' => '1100',
        ]);

        $certificate = null;

        if ($setting->signingCertificate()) {
            $certificate = new Certificate($setting->signingCertificate());
        }

        return view('zatca.settings', [
            'setting' => $setting,
            'certificate' => $certificate,
            'environments' => config('zatca.environments'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'environment' => ['required', Rule::in(array_keys(config('zatca.environments')))],
            'seller_name' => ['required', 'string', 'max:255'],
            'vat_number' => ['required', 'regex:/^3\d{13}3$/'],
            'crn' => ['nullable', 'digits:10'],
            'street' => ['required', 'string', 'max:255'],
            'building_number' => ['required', 'digits:4'],
            'plot_identification' => ['nullable', 'digits:4'],
            'district' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'digits:5'],
            'egs_name' => ['required', 'string', 'max:255'],
            'egs_serial' => ['required', 'regex:/^1-.+\|2-.+\|3-.+$/'],
            'branch_name' => ['required', 'string', 'max:255'],
            'industry' => ['required', 'string', 'max:255'],
            'invoice_types' => ['required', Rule::in(['1100', '1000', '0100'])],
        ], [
            'vat_number.regex' => 'VAT number must be 15 digits, starting and ending with 3.',
            'egs_serial.regex' => 'Serial number format: 1-SolutionName|2-Model|3-SerialNumber',
        ]);

        $setting = ZatcaSetting::current() ?? new ZatcaSetting();
        $setting->fill($data);

        $csrFields = ['environment', 'seller_name', 'vat_number', 'street', 'city', 'egs_name', 'egs_serial', 'branch_name', 'industry', 'invoice_types'];
        $needsNewCsr = $setting->exists && $setting->hasCsr() && $setting->isDirty($csrFields);

        $setting->save();

        return redirect()->route('zatca.edit')->with('success', $needsNewCsr
            ? 'Settings saved. CSR related fields changed, generate a new CSR and onboard again.'
            : 'Settings saved.');
    }

    public function generateCsr(): RedirectResponse
    {
        return $this->step(function (ZatcaSetting $setting) {
            $this->zatca->generateCsr($setting);

            return 'Private key and CSR generated. Now request the Compliance CSID with your OTP.';
        });
    }

    public function complianceCsid(Request $request): RedirectResponse
    {
        $otp = $request->validate(['otp' => ['required', 'digits:6']])['otp'];

        return $this->step(function (ZatcaSetting $setting) use ($otp) {
            $this->zatca->requestComplianceCsid($setting, $otp);

            return 'Compliance CSID received (request ID '.$setting->compliance_request_id.'). Run the compliance checks next.';
        });
    }

    public function complianceChecks(): RedirectResponse
    {
        return $this->step(function (ZatcaSetting $setting) {
            $results = $this->zatca->runComplianceChecks($setting);
            $failed = collect($results)->filter(fn ($result) => ! empty($result['errors']))->count();

            if ($failed) {
                throw new \RuntimeException("$failed compliance check(s) failed. See the results below.");
            }

            return 'All compliance checks passed. Request the Production CSID next.';
        });
    }

    public function productionCsid(): RedirectResponse
    {
        return $this->step(function (ZatcaSetting $setting) {
            $this->zatca->requestProductionCsid($setting);

            return 'Production CSID received. Your EGS unit is onboarded and ready to report/clear invoices.';
        });
    }

    private function step(callable $callback): RedirectResponse
    {
        $setting = ZatcaSetting::current();

        if (! $setting) {
            return back()->with('error', 'Save the seller and EGS settings first.');
        }

        try {
            return back()->with('success', $callback($setting));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }
    }
}
