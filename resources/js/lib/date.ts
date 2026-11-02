const pad = (value: number): string => String(value).padStart(2, '0');

/** "Oct 27, 2026, 3:30 PM" in the viewer's locale and time zone. */
export function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);

    return Number.isNaN(date.getTime())
        ? '—'
        : new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(date);
}

/** An ISO timestamp as the value of a <input type="datetime-local"> (viewer's local time). */
export function isoToLocalInput(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** The value of a datetime-local input (viewer's local time) as a UTC ISO timestamp the server stores. */
export function localInputToIso(value: string): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? '' : date.toISOString();
}

export function viewerTimeZone(): string {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
}

/** "3 hours ago", "in 2 days": a short, human distance from now. */
export function formatRelative(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const seconds = (new Date(iso).getTime() - Date.now()) / 1000;

    if (Number.isNaN(seconds)) {
        return '—';
    }

    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31_536_000],
        ['month', 2_592_000],
        ['week', 604_800],
        ['day', 86_400],
        ['hour', 3600],
        ['minute', 60],
    ];
    const format = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return format.format(Math.round(seconds / size), unit);
        }
    }

    return 'just now';
}

/** "Sat, Mar 14, 6:00 PM" in the event's own time zone (the date only, for an all-day event). */
export function formatEventStart(
    iso: string,
    timeZone: string,
    allDay: boolean,
): string {
    return new Intl.DateTimeFormat(undefined, {
        timeZone,
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        ...(allDay ? {} : { hour: 'numeric', minute: '2-digit' }),
    }).format(new Date(iso));
}
