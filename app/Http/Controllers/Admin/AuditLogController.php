<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The audit trail: who did what, and what changed. Read-only and for super administrators only (the
 * routes sit behind `can:admin`); there is deliberately no way to edit or delete an entry here.
 */
class AuditLogController extends Controller
{
    private const PER_PAGE = 25;

    /** @var array<string, string> */
    public const EVENTS = [
        'created' => 'Created',
        'updated' => 'Updated',
        'trashed' => 'Moved to trash',
        'restored' => 'Restored',
        'deleted' => 'Deleted',
        'login' => 'Signed in',
        'logout' => 'Signed out',
        'login_failed' => 'Failed sign-in',
        'exported' => 'Exported',
        'resent_receipt' => 'Resent receipt',
    ];

    /** @var array<string, string> */
    public const TYPES = [
        'story' => 'Story',
        'event' => 'Event',
        'inquiry' => 'Inquiry',
        'inquiry_type' => 'Inquiry topic',
        'media' => 'Media',
        'setting' => 'Settings',
        'user' => 'User',
        'role' => 'Role',
        'donation' => 'Donation',
        'auth' => 'Sign-in',
    ];

    public function index(Request $request): Response
    {
        $filters = [
            'user' => (string) $request->query('user', ''),
            'event' => (string) $request->query('event', ''),
            'type' => (string) $request->query('type', ''),
            'search' => trim((string) $request->query('search', '')),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ];

        $logs = $this->filtered(AuditLog::query(), $filters)
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (AuditLog $log): array => $this->row($log));

        return Inertia::render('audit-logs/index', [
            'logs' => $logs,
            'filters' => $filters,
            'users' => User::query()->orderBy('name')->get(['id', 'name'])->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])->all(),
            'events' => self::EVENTS,
            'types' => self::TYPES,
        ]);
    }

    /**
     * Everything about one entry, for the detail window. Loaded on demand so the list stays light.
     */
    public function show(AuditLog $auditLog): JsonResponse
    {
        $old = $auditLog->old_values ?? [];
        $new = $auditLog->new_values ?? [];

        // One row per field, in the order they were recorded; a field missing on one side shows as empty.
        $fields = [];

        foreach (array_unique([...array_keys($new), ...array_keys($old)]) as $field) {
            $fields[] = [
                'field' => (string) $field,
                'old' => $old[$field] ?? null,
                'new' => $new[$field] ?? null,
                'in_old' => array_key_exists($field, $old),
                'in_new' => array_key_exists($field, $new),
            ];
        }

        return response()->json(['data' => [
            ...$this->row($auditLog),
            'auditable_id' => $auditLog->auditable_id,
            'user_agent' => $auditLog->user_agent,
            'method' => $auditLog->method,
            'url' => $auditLog->url,
            // The window shows a column only when the entry has something to put in it: a creation has
            // no "old" side, a deletion has no "new" side.
            'has_old' => $old !== [],
            'has_new' => $new !== [],
            'fields' => $fields,
        ]]);
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @param  array<string, string>  $filters
     * @return Builder<AuditLog>
     */
    private function filtered(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['user'] === 'none', fn (Builder $q) => $q->whereNull('user_id'))
            ->when(ctype_digit($filters['user']), fn (Builder $q) => $q->where('user_id', (int) $filters['user']))
            ->when(isset(self::EVENTS[$filters['event']]), fn (Builder $q) => $q->where('event', $filters['event']))
            ->when(isset(self::TYPES[$filters['type']]), fn (Builder $q) => $q->where('auditable_type', $filters['type']))
            ->when($filters['from'] !== '' && strtotime($filters['from']) !== false, fn (Builder $q) => $q->where('created_at', '>=', date('Y-m-d 00:00:00', (int) strtotime($filters['from']))))
            ->when($filters['to'] !== '' && strtotime($filters['to']) !== false, fn (Builder $q) => $q->where('created_at', '<=', date('Y-m-d 23:59:59', (int) strtotime($filters['to']))))
            ->when($filters['search'] !== '', function (Builder $q) use ($filters): void {
                $like = '%'.addcslashes($filters['search'], '%_\\').'%';

                $q->where(fn (Builder $inner) => $inner
                    ->where('auditable_label', 'like', $like)
                    ->orWhere('actor_name', 'like', $like)
                    ->orWhere('actor_email', 'like', $like)
                    ->orWhere('ip_address', 'like', $like));
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function row(AuditLog $log): array
    {
        $changed = array_keys($log->new_values ?? $log->old_values ?? []);

        return [
            'id' => $log->id,
            'created_at' => $log->created_at->toIso8601String(),
            'actor' => [
                'id' => $log->user_id,
                'name' => $log->actor_name,
                'email' => $log->actor_email,
                'source' => $log->source,
            ],
            'event' => $log->event,
            'type' => $log->auditable_type,
            'label' => $log->auditable_label,
            'ip_address' => $log->ip_address,
            'changed' => array_slice($changed, 0, 4),
            'changed_count' => count($changed),
        ];
    }
}
