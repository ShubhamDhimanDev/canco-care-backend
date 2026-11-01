<?php

namespace App\Services\Donations;

use App\Contracts\DonationGateway;

/**
 * Picks the payment provider from DONATIONS_GATEWAY. Today only the fake gateway exists: the Global
 * Payments integration waits for sandbox credentials (open question Q-P1), and a production server
 * therefore has no usable gateway, so donations stay closed there.
 */
final class DonationGateways
{
    /**
     * Whether a donation can be taken right now.
     */
    public static function available(): bool
    {
        return match (config('services.donations.gateway')) {
            'fake' => ! app()->isProduction(),
            default => false,
        };
    }

    public static function make(): DonationGateway
    {
        if (! self::available()) {
            throw new GatewayException('No payment gateway is configured.');
        }

        return new FakeGateway;
    }

    /**
     * Why donations cannot be switched on, for the administrator; null when they can.
     */
    public static function problem(): ?string
    {
        return match (true) {
            self::available() => null,
            config('services.donations.gateway') === 'fake' => 'The test gateway cannot be used on a live (production) server.',
            config('services.donations.gateway') === 'global_payments' => 'The Global Payments integration has not been built yet; it needs the sandbox credentials first.',
            default => 'No payment gateway is configured on the server (DONATIONS_GATEWAY).',
        };
    }
}
