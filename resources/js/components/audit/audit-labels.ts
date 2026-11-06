import type { AuditRow } from '@/types';

/** "Nov 7, 2026, 3:04:12 PM" in the viewer's locale and time zone. */
export function formatWhen(iso: string): string {
    const date = new Date(iso);

    return Number.isNaN(date.getTime())
        ? '—'
        : new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'medium',
          }).format(date);
}

/** What to call an actor who is not a named person. */
export function sourceLabel(source: AuditRow['actor']['source']): string {
    switch (source) {
        case 'website':
            return 'Website visitor';
        case 'console':
            return 'System (command line / scheduler)';
        case 'guest':
            return 'Not signed in';
        default:
            return 'Unknown';
    }
}

/** Badge colours: green = added, amber = changed, red = removed or failed, neutral = everything else. */
export function eventTone(event: string): string {
    switch (event) {
        case 'created':
        case 'restored':
            return 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300';
        case 'updated':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300';
        case 'trashed':
        case 'deleted':
        case 'login_failed':
            return 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300';
        default:
            return 'bg-muted text-muted-foreground';
    }
}
