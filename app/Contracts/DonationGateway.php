<?php

namespace App\Contracts;

use App\Models\Donation;
use App\Services\Donations\GatewayException;
use App\Services\Donations\GatewayResult;

/**
 * The payment provider behind a donation (docs/plan/08-feature-donations.md §4). The browser
 * collects card details directly with the provider and hands this server a single-use token: card
 * numbers never reach Laravel, and the amount charged is always the one stored on the donation.
 */
interface DonationGateway
{
    /** Stored on the donation ("fake", "global_payments"). */
    public function name(): string;

    /** True for a sandbox or fake provider: nothing real is charged and receipts say so. */
    public function isTest(): bool;

    /**
     * What the browser needs to start collecting payment (never a secret).
     *
     * @return array<string, mixed>
     */
    public function session(Donation $donation): array;

    /**
     * Charges the stored amount using the browser's single-use token.
     *
     * @throws GatewayException when the provider could not be reached or answered unusably
     */
    public function charge(Donation $donation, string $paymentToken): GatewayResult;
}
