<?php

namespace App\Domains\Settlement\Http\Controllers;

use App\Domains\Quotes\Settings\QuotationSettings;
use App\Domains\Settlement\Actions\PayoutService;
use App\Domains\Settlement\Models\Invoice;
use App\Domains\Settlement\Models\Payout;
use App\Domains\Settlement\Models\SettlementCase;
use App\Http\Controllers\Controller;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;

/** Seller payouts (payouts.record / payouts.approve), invoices and transfer stages. */
class PayoutController extends Controller
{
    public function prepare(Request $request, SettlementCase $case, PayoutService $service, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate(['payee' => ['required', 'string', 'max:160'], 'payee_reference' => ['nullable', 'string', 'max:160']]);
        $service->prepare($case, $data['payee'], $data['payee_reference'] ?? null, $actor);

        return $this->done('Payout prepared. Another finance user must approve it.');
    }

    public function approve(Payout $payout, PayoutService $service, #[CurrentUser] User $actor): RedirectResponse
    {
        $service->approve($payout, $actor);

        return $this->done('Payout approved. Pay it at the bank, then record the payment.');
    }

    public function paid(Request $request, Payout $payout, PayoutService $service, #[CurrentUser] User $actor): RedirectResponse
    {
        $request->validate(['proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']], ['proof.required' => __('Upload the bank’s payment confirmation.')]);
        /** @var UploadedFile $proof */
        $proof = $request->file('proof');
        $service->markPaid($payout, $proof, $actor);

        return $this->done('Payout recorded as paid.');
    }

    public function invoice(Request $request, SettlementCase $case, PayoutService $service, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate(['recipient' => ['required', 'string', 'max:160'], 'recipient_address' => ['nullable', 'string', 'max:500'], 'recipient_vat_number' => ['nullable', 'regex:/^4\d{9}$/']],
            ['recipient_vat_number.regex' => __('A South African VAT number has 10 digits and starts with 4.')]);
        $invoice = $service->issueInvoice($case, $data['recipient'], $data['recipient_address'] ?? null, $data['recipient_vat_number'] ?? null, $actor);

        return $this->done(__('Tax invoice :n issued.', ['n' => $invoice->number]));
    }

    public function invoicePdf(Invoice $invoice): HttpResponse
    {
        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice, 'company' => app(QuotationSettings::class), 'tz' => (string) config('platform.display_timezone')])->setPaper('a4')->output();

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$invoice->number.'.pdf"', 'Cache-Control' => 'no-store, private']);
    }

    public function stage(Request $request, SettlementCase $case, PayoutService $service, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate(['stage' => ['required', 'string'], 'done_on' => ['required', 'date', 'before_or_equal:today'], 'note' => ['nullable', 'string', 'max:500']]);
        $service->recordStage($case, $data['stage'], $data['done_on'], $data['note'] ?? null, $actor);

        return $this->done('Transfer stage recorded.');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);

        return back();
    }
}
