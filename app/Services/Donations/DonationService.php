<?php

namespace App\Services\Donations;

use App\Contracts\DonationGateway;
use App\Enums\DonationStatus;
use App\Jobs\RevalidateFrontend;
use App\Jobs\SendDonationReceipt;
use App\Models\Donation;
use App\Models\Event;
use App\Support\ClientIp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * The donation state machine (docs/plan/08-feature-donations.md §4). Two rules are enforced here and
 * nowhere else:
 *
 *  - the amount charged is the one stored when the donation was created, never one sent later;
 *  - a donation is charged at most once: confirming it again returns its current state.
 */
class DonationService
{
    /**
     * The gateway is resolved only when a donation is actually created or charged, so a server with
     * no gateway configured can still answer "donations are closed" instead of failing to start.
     */
    private function gateway(): DonationGateway
    {
        return app(DonationGateway::class);
    }

    /**
     * Creates a pending donation, or returns the one this attempt already made (same idempotency
     * key), so a double click or a retried request never makes a second record.
     *
     * @param  array<string, mixed>  $data  validated StoreDonationRequest input
     * @return array{0: Donation, 1: bool} the donation and whether it was just created
     */
    public function create(array $data, Request $request): array
    {
        $existing = Donation::where('idempotency_key', $data['idempotency_key'])->first();

        if ($existing !== null) {
            return [$existing, false];
        }

        $event = filled($data['event'] ?? null)
            ? Event::visible()->where('show_in_donation_form', true)->where('slug', $data['event'])->first()
            : null;

        $donation = DB::transaction(function () use ($data, $request, $event): Donation {
            $donation = Donation::create([
                'reference' => (string) Str::uuid(),
                'status' => DonationStatus::Pending,
                'amount_cents' => (int) $data['amount_cents'],
                'currency' => 'CAD',
                'event_id' => $event?->id,
                'event_title' => $event?->title,
                'donor_first_name' => $data['first_name'],
                'donor_last_name' => $data['last_name'],
                'donor_email' => $data['email'],
                'donor_phone' => $data['phone'] ?? null,
                'address_line1' => $data['address_line1'],
                'address_line2' => $data['address_line2'] ?? null,
                'city' => $data['city'],
                'province' => $data['province'],
                'postal_code' => $data['postal_code'],
                'country' => 'CA',
                'consent_at' => now(),
                'gateway' => $this->gateway()->name(),
                'is_test' => $this->gateway()->isTest(),
                'idempotency_key' => $data['idempotency_key'],
                'ip_hash' => ClientIp::hash(ClientIp::for($request)),
            ]);

            $this->record($donation, 'created', ['amount_cents' => $donation->amount_cents, 'event' => $event?->slug]);

            return $donation;
        });

        $this->record($donation, 'session_created', ['gateway' => $this->gateway()->name()]);

        return [$donation, true];
    }

    /**
     * What the browser needs to collect payment.
     *
     * @return array<string, mixed>
     */
    public function session(Donation $donation): array
    {
        return $this->gateway()->session($donation);
    }

    /**
     * Charges a pending donation with the browser's single-use token. Anything that is not pending
     * is returned as it is, uncharged.
     *
     * @throws GatewayException when the provider could not be reached; the donation stays pending
     */
    public function confirm(Donation $donation, string $paymentToken): Donation
    {
        // One confirmation at a time per donation, even across servers.
        return Cache::lock("donation-confirm:{$donation->id}", 30)->block(5, function () use ($donation, $paymentToken): Donation {
            $donation->refresh();

            if ($donation->status !== DonationStatus::Pending) {
                return $donation;
            }

            $this->record($donation, 'charge_attempted');

            try {
                $result = $this->gateway()->charge($donation, $paymentToken);
            } catch (Throwable $exception) {
                // The donor may or may not have been charged: leave it pending for reconciliation.
                $this->record($donation, 'charge_error', ['error' => class_basename($exception)]);

                throw $exception instanceof GatewayException ? $exception : new GatewayException('The payment provider did not answer.', 0, $exception);
            }

            return $result->succeeded() ? $this->succeed($donation, $result) : $this->fail($donation, $result);
        });
    }

    private function succeed(Donation $donation, GatewayResult $result): Donation
    {
        $this->assertCan($donation, DonationStatus::Succeeded);

        DB::transaction(function () use ($donation, $result): void {
            $donation->forceFill([
                'status' => DonationStatus::Succeeded,
                'paid_at' => now(),
                'gateway_transaction_id' => $result->transactionId,
                'gateway_response_code' => $result->code,
                'gateway_response_message' => $result->message,
                'card_brand' => $result->cardBrand,
                'card_last4' => $result->cardLast4,
                'failure_reason' => null,
                'receipt_number' => $this->receiptNumber($donation),
                'receipt_issued_at' => now(),
            ])->save();

            $this->record($donation, 'charge_succeeded', ['transaction' => $result->transactionId, 'receipt' => $donation->receipt_number]);
        });

        // The receipt email, and the website's "impact" figure if it is set to follow donations.
        SendDonationReceipt::dispatch($donation->id)->afterCommit();
        RevalidateFrontend::fire(['settings']);

        return $donation;
    }

    private function fail(Donation $donation, GatewayResult $result): Donation
    {
        $this->assertCan($donation, DonationStatus::Failed);

        $donation->forceFill([
            'status' => DonationStatus::Failed,
            'gateway_response_code' => $result->code,
            'gateway_response_message' => $result->message,
            'failure_reason' => $result->failureReason ?? 'The payment could not be completed.',
        ])->save();

        $this->record($donation, 'charge_failed', ['code' => $result->code]);

        return $donation;
    }

    private function assertCan(Donation $donation, DonationStatus $to): void
    {
        if (! $donation->status->canBecome($to)) {
            throw new \LogicException("A {$donation->status->value} donation cannot become {$to->value}.");
        }
    }

    /**
     * "2026-000123": the year of the gift and the donation's own (never reused) id, so every receipt
     * has a unique serial number that can be traced back.
     */
    private function receiptNumber(Donation $donation): string
    {
        return sprintf('%s-%06d', now(Donation::RECEIPT_TIMEZONE)->format('Y'), $donation->id);
    }

    /**
     * @param  array<string, mixed>|null  $payload  never a card number, token or secret
     */
    private function record(Donation $donation, string $type, ?array $payload = null): void
    {
        $donation->trail()->create(['type' => $type, 'payload' => $payload]);
    }
}
