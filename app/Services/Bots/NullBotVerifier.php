<?php

namespace App\Services\Bots;

use App\Contracts\BotVerifier;

/**
 * Used until Turnstile keys are configured (open question Q-S2). It accepts every request; the
 * honeypot field and the rate limits still apply.
 */
class NullBotVerifier implements BotVerifier
{
    public function enabled(): bool
    {
        return false;
    }

    public function passes(?string $token, ?string $ip): bool
    {
        return true;
    }
}
