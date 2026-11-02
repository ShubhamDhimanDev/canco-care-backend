<?php

namespace App\Services\Bots;

use App\Contracts\BotVerifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cloudflare Turnstile server-side verification. An unreachable Cloudflare counts as a failure
 * (fail closed), so an outage cannot be used to skip the check.
 */
class TurnstileVerifier implements BotVerifier
{
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(private readonly string $secret) {}

    public function enabled(): bool
    {
        return true;
    }

    public function passes(?string $token, ?string $ip): bool
    {
        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::ENDPOINT, array_filter([
                'secret' => $this->secret,
                'response' => $token,
                'remoteip' => $ip,
            ]));

            return $response->ok() && $response->json('success') === true;
        } catch (Throwable $exception) {
            Log::warning('Turnstile verification failed to run.', ['error' => $exception->getMessage()]);

            return false;
        }
    }
}
