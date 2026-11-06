<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\ClientIp;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes the audit trail (who did what, with the old and new values). Used by the `Audited` model trait
 * and by the few actions that are not a model change (sign-in, exports, a settings save, role edits).
 *
 * Two rules: secrets never reach the log (see REDACTED), and a failure to write the log never breaks the
 * action being logged.
 */
class Audit
{
    /** Never shown: they change on their own or say nothing useful. */
    public const IGNORED = ['id', 'created_at', 'updated_at', 'deleted_at', 'remember_token'];

    /** Shown only as "[hidden]", so the log says THAT it changed, never to what. */
    public const REDACTED = ['password', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** What the screen shows instead of a secret. */
    public const HIDDEN = '[hidden]';

    /**
     * @param  array<string, mixed>|null  $old  values before (null when there is nothing to compare, as on create)
     * @param  array<string, mixed>|null  $new  values after (null on delete)
     */
    public function record(
        string $event,
        string $type,
        int|string|null $id = null,
        ?string $label = null,
        ?array $old = null,
        ?array $new = null,
        ?User $actor = null,
    ): ?AuditLog {
        try {
            $actor ??= auth()->user();
            $actor = $actor instanceof User ? $actor : null;
            $request = app()->runningInConsole() ? null : request();

            return AuditLog::create([
                'user_id' => $actor?->id,
                'actor_name' => $actor?->name,
                'actor_email' => $actor?->email,
                'source' => $this->source($actor, $request),
                'event' => $event,
                'auditable_type' => $type,
                'auditable_id' => $id === null ? null : (string) $id,
                'auditable_label' => $label === null ? null : Str::limit($label, 250, ''),
                'old_values' => $this->clean($old),
                'new_values' => $this->clean($new),
                'ip_address' => $request === null ? null : ClientIp::for($request),
                'user_agent' => $request === null ? null : Str::limit((string) $request->userAgent(), 250, ''),
                'method' => $request?->getMethod(),
                'url' => $request === null ? null : Str::limit($request->path(), 490, ''),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * The fields whose value differs, as [old, new] pairs of plain values. Used where a change is made
     * without Eloquent events (settings, roles).
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function diff(array $before, array $after): array
    {
        $old = [];
        $new = [];

        foreach ($after as $key => $value) {
            $was = $before[$key] ?? null;

            if ($this->normalise($was) !== $this->normalise($value)) {
                $old[$key] = $was;
                $new[$key] = $value;
            }
        }

        return [$old, $new];
    }

    /**
     * admin: a signed-in person · website: the public site's API (a visitor) · console: artisan, scheduler,
     * queue · guest: someone not signed in (a failed login).
     */
    private function source(?User $actor, ?Request $request): string
    {
        return match (true) {
            $actor !== null => 'admin',
            $request === null => 'console',
            $request->is('api/*') => 'website',
            default => 'guest',
        };
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function clean(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $clean = [];

        foreach ($values as $key => $value) {
            if (in_array($key, self::IGNORED, true)) {
                continue;
            }

            $clean[$key] = in_array($key, self::REDACTED, true) ? self::HIDDEN : $this->normalise($value);
        }

        return $clean === [] ? null : $clean;
    }

    /**
     * Plain JSON-safe values (enums, dates and objects become what they serialise to) with long text cut.
     */
    private function normalise(mixed $value): mixed
    {
        $value = json_decode((string) json_encode($value), true);

        return $this->truncate($value);
    }

    private function truncate(mixed $value): mixed
    {
        $max = (int) config('audit.max_value_length', 20000);

        if (is_string($value) && mb_strlen($value) > $max) {
            return mb_substr($value, 0, $max).'… [cut: '.(mb_strlen($value) - $max).' more characters]';
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->truncate($item), $value);
        }

        return $value;
    }
}
