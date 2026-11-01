/**
 * Small helpers for the few calls that cannot go through Inertia (uploads with progress, the media
 * picker's JSON feed). Laravel puts the CSRF token in the XSRF-TOKEN cookie; sending it back in the
 * X-XSRF-TOKEN header is how it recognises a legitimate same-site request.
 */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export type JsonResult<T> =
    | { ok: true; data: T }
    | { ok: false; message: string; errors: Record<string, string[]> };

/** POST a FormData and read a JSON response, turning validation errors into a plain message. */
export async function postFormData<T>(
    url: string,
    body: FormData,
): Promise<JsonResult<T>> {
    const response = await fetch(url, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
    });

    const json = await response.json().catch(() => null);

    if (response.ok && json) {
        return { ok: true, data: json.data as T };
    }

    const errors = (json?.errors ?? {}) as Record<string, string[]>;
    const first = Object.values(errors)[0]?.[0];

    return {
        ok: false,
        errors,
        message:
            first ??
            json?.message ??
            (response.status === 413
                ? 'That file is too large for the server.'
                : 'The upload failed. Please try again.'),
    };
}

export async function getJson<T>(url: string): Promise<T> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error(`Request failed (${response.status})`);
    }

    return (await response.json()) as T;
}

export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
