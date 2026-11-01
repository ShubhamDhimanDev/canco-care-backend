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
