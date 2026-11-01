<?php

namespace App\Contracts;

/**
 * Checks the "are you a person" token the website sends with a public form (Cloudflare Turnstile).
 * Verification happens on the server: a client-side check alone proves nothing.
 */
interface BotVerifier
{
    /**
     * Whether the form must include a token at all. False when no keys are configured yet, so the
     * form still works (with the honeypot and rate limits) until Turnstile is set up.
     */
    public function enabled(): bool;

    public function passes(?string $token, ?string $ip): bool;
}
