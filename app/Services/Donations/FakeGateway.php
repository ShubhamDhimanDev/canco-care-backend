<?php

namespace App\Services\Donations;

use App\Contracts\DonationGateway;
use App\Enums\DonationStatus;
use App\Models\Donation;
use Illuminate\Support\Str;
use LogicException;

/**
 * A pretend payment provider for development, staging and tests. It charges nothing. The website
 * shows two buttons in test mode that send `fake-approve` or `fake-decline`.
 *
 * It refuses to exist in production: a live site must never mark gifts as paid without taking money.
 */
class FakeGateway implements DonationGateway
{
    public const APPROVE = 'fake-approve';

    public const DECLINE = 'fake-decline';

    public function __construct()
    {
        if (app()->isProduction()) {
            throw new LogicException('The fake donation gateway must never run in production.');
        }
    }

    public function name(): string
    {
        return 'fake';
    }

    public function isTest(): bool
    {
        return true;
    }

    public function session(Donation $donation): array
    {
        return ['provider' => 'fake', 'test_mode' => true];
    }

    public function charge(Donation $donation, string $paymentToken): GatewayResult
    {
        return match ($paymentToken) {
            self::APPROVE => new GatewayResult(
                status: DonationStatus::Succeeded,
                transactionId: 'fake_'.Str::lower(Str::random(14)),
                code: '00',
                message: 'Approved (test)',
                cardBrand: 'Visa',
                cardLast4: '4242',
            ),
            self::DECLINE => new GatewayResult(
                status: DonationStatus::Failed,
                code: '05',
                message: 'Declined (test)',
                failureReason: 'Your card was declined. Please try another card.',
            ),
            default => new GatewayResult(
                status: DonationStatus::Failed,
                code: 'invalid_token',
                message: 'Unrecognised test token',
                failureReason: 'The payment details were not recognised. Please try again.',
            ),
        };
    }
}
