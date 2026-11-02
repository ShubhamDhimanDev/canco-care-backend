<?php

namespace App\Support;

use App\Http\Middleware\EnsureFrontendApiKey;
use Illuminate\Http\Request;

/**
 * The visitor's address for rate limits and spam checks. The website's server makes every API call,
 * so Laravel would see one address for everyone; the server forwards the visitor's in
 * X-Forwarded-For, and that header is believed only on requests carrying the valid frontend key.
 */
final class ClientIp
{
    public static function for(Request $request): string
    {
        if (EnsureFrontendApiKey::isValid($request)) {
            $forwarded = trim(explode(',', (string) $request->header('X-Forwarded-For', ''))[0]);

            if (filter_var($forwarded, FILTER_VALIDATE_IP) !== false) {
                return $forwarded;
            }
        }

        return (string) $request->ip();
    }

    /**
     * A salted hash: enough to spot repeat senders, not enough to recover the address.
     */
    public static function hash(string $ip): string
    {
        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }
}
