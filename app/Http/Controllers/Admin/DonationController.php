<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DonationStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SendDonationReceipt;
use App\Models\Donation;
use App\Services\Audit;
use App\Services\Donations\ReceiptData;
use App\Services\Settings;
use App\Support\CsvExport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Donations: a read-only ledger with the audit trail, receipt preview/resend and a CSV export.
 * Administrators only (the routes sit behind the `admin` gate): donors' details are never shown to
 * editors.
 */
class DonationController extends Controller
{
    private const VIEWS = ['all', 'succeeded', 'pending', 'failed', 'refunded'];

    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        [$view, $search, $from, $to, $mode] = $this->filters($request);

        $query = $this->filtered(Donation::query(), $view, $search, $from, $to, $mode);

        $received = (clone $query)->whereIn('status', [DonationStatus::Succeeded->value, DonationStatus::PartiallyRefunded->value]);
        $gifts = $received->count();
        $cents = (int) $received->sum(DB::raw('amount_cents - refunded_cents'));

        $donations = $query
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Donation $donation): array => $this->row($donation));

        return Inertia::render('donations/index', [
            'donations' => $donations,
            'filters' => ['view' => $view, 'search' => $search, 'from' => $from, 'to' => $to, 'mode' => $mode],
            'counts' => array_combine(self::VIEWS, array_map(
                fn (string $name): int => $this->filtered(Donation::query(), $name, $search, $from, $to, $mode)->count(),
                self::VIEWS,
            )),
            'totals' => ['gifts' => $gifts, 'cents' => $cents],
        ]);
    }

    public function show(Donation $donation): Response
    {
        $donation->load('trail');

        return Inertia::render('donations/show', [
            'donation' => [
                ...$this->row($donation),
                'reference' => $donation->reference,
                'email' => $donation->donor_email,
                'phone' => $donation->donor_phone,
                'address' => array_values(array_filter([
                    $donation->address_line1,
                    $donation->address_line2,
                    trim("{$donation->city}, {$donation->province} {$donation->postal_code}"),
                ])),
                'gateway' => $donation->gateway,
                'transaction_id' => $donation->gateway_transaction_id,
                'response' => trim(($donation->gateway_response_code ?? '').' '.($donation->gateway_response_message ?? '')),
                'failure_reason' => $donation->failure_reason,
                'card' => $donation->card_brand ? "{$donation->card_brand} •••• {$donation->card_last4}" : null,
                'refunded_cents' => $donation->refunded_cents,
                'receipt_issued_at' => $donation->receipt_issued_at?->toIso8601String(),
                'receipt_sent_at' => $donation->receipt_sent_at?->toIso8601String(),
                'consent_at' => $donation->consent_at->toIso8601String(),
                'can_resend' => $donation->receipt_number !== null && $donation->status->counts(),
                'trail' => $donation->trail->map(fn ($event): array => [
                    'id' => $event->id,
                    'type' => $event->type,
                    'payload' => $event->payload,
                    'at' => $event->created_at?->toIso8601String(),
                ])->all(),
            ],
        ]);
    }

    /**
     * The receipt exactly as the donor gets it, for the administrator to check.
     */
    public function receipt(Donation $donation, Settings $settings): View
    {
        abort_if($donation->receipt_number === null, 404, 'This donation has no receipt yet.');

        return view('receipts.donation', ['receipt' => ReceiptData::for($donation, $settings)]);
    }

    public function resend(Donation $donation): RedirectResponse
    {
        abort_unless($donation->receipt_number !== null && $donation->status->counts(), 404);

        SendDonationReceipt::dispatch($donation->id);

        app(Audit::class)->record('resent_receipt', 'donation', $donation->id, $donation->reference, null, ['sent_to' => $donation->donor_email, 'receipt_number' => $donation->receipt_number]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The receipt is on its way to :email.', ['email' => $donation->donor_email])]);

        return back();
    }

    public function export(Request $request): StreamedResponse
    {
        [$view, $search, $from, $to, $mode] = $this->filters($request);
        $query = $this->filtered(Donation::query(), $view, $search, $from, $to, $mode)->orderByDesc('created_at');

        app(Audit::class)->record('exported', 'donation', null, 'Donations CSV', null, ['filters' => array_filter($request->query()), 'rows' => (clone $query)->count()]);

        return CsvExport::download(
            'donations-'.now()->format('Y-m-d').'.csv',
            ['Reference', 'Date', 'Status', 'Test', 'Amount (CAD)', 'Refunded (CAD)', 'Event', 'First name', 'Last name', 'Email', 'Phone', 'Street', 'City', 'Province', 'Postal code', 'Receipt #', 'Receipt sent'],
            $query,
            fn (Donation $donation): array => [
                $donation->reference,
                ($donation->paid_at ?? $donation->created_at)?->setTimezone(Donation::RECEIPT_TIMEZONE)->toDateTimeString(),
                $donation->status->label(),
                $donation->is_test ? 'Yes' : 'No',
                Donation::plainAmount($donation->amount_cents),
                Donation::plainAmount($donation->refunded_cents),
                $donation->event_title,
                $donation->donor_first_name,
                $donation->donor_last_name,
                $donation->donor_email,
                $donation->donor_phone,
                trim($donation->address_line1.' '.($donation->address_line2 ?? '')),
                $donation->city,
                $donation->province,
                $donation->postal_code,
                $donation->receipt_number,
                $donation->receipt_sent_at?->toDateTimeString(),
            ],
        );
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string}
     */
    private function filters(Request $request): array
    {
        $view = in_array($request->query('view'), self::VIEWS, true) ? (string) $request->query('view') : 'all';
        $date = fn (string $key): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($key)) ? (string) $request->query($key) : '';
        $mode = in_array($request->query('mode'), ['real', 'test'], true) ? (string) $request->query('mode') : 'all';

        return [$view, trim((string) $request->query('search', '')), $date('from'), $date('to'), $mode];
    }

    /**
     * @param  Builder<Donation>  $query
     * @return Builder<Donation>
     */
    private function filtered(Builder $query, string $view, string $search, string $from, string $to, string $mode): Builder
    {
        return $query
            ->when($view === 'refunded', fn (Builder $q) => $q->whereIn('status', [DonationStatus::Refunded->value, DonationStatus::PartiallyRefunded->value]))
            ->when(in_array($view, ['succeeded', 'pending', 'failed'], true), fn (Builder $q) => $q->where('status', $view))
            ->when($mode !== 'all', fn (Builder $q) => $q->where('is_test', $mode === 'test'))
            ->when($from !== '', fn (Builder $q) => $q->where('created_at', '>=', CarbonImmutable::parse($from, Donation::RECEIPT_TIMEZONE)->startOfDay()->utc()))
            ->when($to !== '', fn (Builder $q) => $q->where('created_at', '<=', CarbonImmutable::parse($to, Donation::RECEIPT_TIMEZONE)->endOfDay()->utc()))
            ->when($search !== '', fn (Builder $q) => $q->where(
                fn (Builder $s) => $s->where('donor_first_name', 'like', "%{$search}%")
                    ->orWhere('donor_last_name', 'like', "%{$search}%")
                    ->orWhere('donor_email', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('receipt_number', 'like', "%{$search}%")
                    ->orWhere('event_title', 'like', "%{$search}%")
            ));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Donation $donation): array
    {
        return [
            'id' => $donation->id,
            'short_reference' => strtoupper(substr($donation->reference, 0, 8)),
            'status' => $donation->status->value,
            'is_test' => $donation->is_test,
            'amount_cents' => $donation->amount_cents,
            'donor' => $donation->donorName(),
            'event' => $donation->event_title,
            'receipt_number' => $donation->receipt_number,
            'date' => ($donation->paid_at ?? $donation->created_at)?->toIso8601String(),
        ];
    }
}
