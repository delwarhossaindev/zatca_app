<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ZatcaSetting;
use App\Services\Zatca\ZatcaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InvoiceController extends Controller
{
    private const NUMBER_PREFIXES = ['invoice' => 'INV', 'credit' => 'CRN', 'debit' => 'DBN'];

    public function __construct(private ZatcaService $zatca)
    {
    }

    public function index(): View
    {
        return view('invoices.index', [
            'invoices' => Invoice::query()->latest('id')->paginate(20),
            'setting' => ZatcaSetting::current(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('invoices.create', [
            'setting' => ZatcaSetting::current(),
            'original' => $request->filled('original') ? Invoice::find($request->integer('original')) : null,
            'documentType' => $request->query('document_type', 'invoice'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $standard = $request->input('invoice_type') === Invoice::TYPE_STANDARD;

        $data = $request->validate([
            'invoice_type' => ['required', Rule::in([Invoice::TYPE_STANDARD, Invoice::TYPE_SIMPLIFIED])],
            'document_type' => ['required', Rule::in(array_keys(Invoice::DOCUMENT_CODES))],
            'issue_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_means' => ['required', Rule::in(['10', '30', '42', '48', '1'])],
            'original_invoice_number' => ['required_unless:document_type,invoice', 'nullable', 'string', 'max:255'],
            'note_reason' => ['required_unless:document_type,invoice', 'nullable', 'string', 'max:1000'],
            'buyer_name' => [Rule::requiredIf($standard), 'nullable', 'string', 'max:255'],
            'buyer_vat_number' => ['nullable', 'regex:/^3\d{13}3$/'],
            'buyer_street' => [Rule::requiredIf($standard), 'nullable', 'string', 'max:255'],
            'buyer_building_number' => [Rule::requiredIf($standard), 'nullable', 'digits:4'],
            'buyer_district' => [Rule::requiredIf($standard), 'nullable', 'string', 'max:255'],
            'buyer_city' => [Rule::requiredIf($standard), 'nullable', 'string', 'max:255'],
            'buyer_postal_code' => [Rule::requiredIf($standard), 'nullable', 'digits:5'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ], [
            'buyer_vat_number.regex' => 'Buyer VAT number must be 15 digits, starting and ending with 3.',
        ]);

        if (! ZatcaSetting::current()?->signingCertificate()) {
            return back()->withInput()->with('error', 'Complete ZATCA onboarding before issuing invoices.');
        }

        try {
            $invoice = DB::transaction(function () use ($data) {
                $invoice = new Invoice(collect($data)->except('items')->all() + [
                    'uuid' => (string) Str::uuid(),
                    'issue_time' => now()->format('H:i:s'),
                ]);
                $invoice->invoice_number = $this->nextNumber($invoice->document_type);

                $items = collect($data['items'])->map(fn ($item) => new InvoiceItem($item + [
                    'vat_category' => 'S',
                    'vat_percent' => 15,
                ]));

                $invoice->calculateTotals($items);
                $invoice->save();
                $invoice->items()->saveMany($items);
                $invoice->setRelation('items', $items);

                $this->zatca->prepare($invoice);

                return $invoice;
            });
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($request->boolean('submit_now')) {
            return $this->submit($invoice);
        }

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice signed and ready to submit.');
    }

    public function show(Invoice $invoice): View
    {
        return view('invoices.show', [
            'invoice' => $invoice->load('items'),
            'setting' => ZatcaSetting::current(),
        ]);
    }

    public function submit(Invoice $invoice): RedirectResponse
    {
        try {
            $result = $this->zatca->submit($invoice);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('invoices.show', $invoice)->with('error', $e->getMessage());
        }

        $action = $invoice->isSimplified() ? 'reporting' : 'clearance';

        return redirect()->route('invoices.show', $invoice)->with(
            empty($result['errors']) ? 'success' : 'error',
            "ZATCA $action result: {$result['status']}"
        );
    }

    public function xml(Invoice $invoice, string $version = 'signed'): Response
    {
        $xml = $version === 'cleared' ? $invoice->cleared_xml : $invoice->signed_xml;
        abort_unless($xml, 404);

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$invoice->invoice_number.'-'.$version.'.xml"',
        ]);
    }

    private function nextNumber(string $documentType): string
    {
        $prefix = self::NUMBER_PREFIXES[$documentType];
        $count = Invoice::query()->where('document_type', $documentType)->count();

        do {
            $number = $prefix.'-'.str_pad((string) ++$count, 6, '0', STR_PAD_LEFT);
        } while (Invoice::query()->where('invoice_number', $number)->exists());

        return $number;
    }
}
