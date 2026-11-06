/** One line of the audit list. */
export type AuditRow = {
    id: number;
    created_at: string;
    actor: {
        id: number | null;
        name: string | null;
        email: string | null;
        /** admin = a signed-in person · website = a visitor via the site's API · console = artisan/scheduler/queue · guest = not signed in */
        source: 'admin' | 'website' | 'console' | 'guest';
    };
    event: string;
    type: string;
    label: string | null;
    ip_address: string | null;
    /** The first few field names that changed. */
    changed: string[];
    changed_count: number;
};

/** One field in the detail window. A field missing on a side is simply absent there (`in_old` / `in_new`). */
export type AuditField = {
    field: string;
    old: unknown;
    new: unknown;
    in_old: boolean;
    in_new: boolean;
};

export type AuditDetail = AuditRow & {
    auditable_id: string | null;
    user_agent: string | null;
    method: string | null;
    url: string | null;
    /** False for a creation: there is no "before". */
    has_old: boolean;
    /** False for a deletion: there is no "after". */
    has_new: boolean;
    fields: AuditField[];
};

export type AuditFilters = {
    user: string;
    event: string;
    type: string;
    search: string;
    from: string;
    to: string;
};
