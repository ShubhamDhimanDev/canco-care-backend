import { useEffect, useState } from 'react';
import AuditLogController from '@/actions/App/Http/Controllers/Admin/AuditLogController';
import { Spinner } from '@/components/ui/spinner';
import { useHydrated } from '@/hooks/use-hydrated';
import { cn } from '@/lib/utils';
import type { AuditDetail, AuditField } from '@/types';
import { formatWhen, sourceLabel } from './audit-labels';

/** "story_status" / "impact.caption" → "story status" / "impact caption". */
function fieldName(field: string): string {
    return field.replace(/[_.]+/g, ' ');
}

/** One stored value: text keeps its line breaks, lists become chips, objects are pretty-printed. */
function Value({ value, present }: { value: unknown; present: boolean }) {
    if (!present || value === null || value === undefined || value === '') {
        return (
            <span className="text-muted-foreground">
                {present ? 'empty' : '—'}
            </span>
        );
    }

    if (typeof value === 'boolean') {
        return <span>{value ? 'Yes' : 'No'}</span>;
    }

    if (
        Array.isArray(value) &&
        value.every((item) => typeof item === 'string')
    ) {
        return value.length === 0 ? (
            <span className="text-muted-foreground">empty list</span>
        ) : (
            <span className="flex flex-wrap gap-1">
                {value.map((item) => (
                    <span
                        key={item}
                        className="rounded bg-muted px-1.5 py-0.5 text-xs"
                    >
                        {item}
                    </span>
                ))}
            </span>
        );
    }

    if (typeof value === 'object') {
        return (
            <pre className="max-h-48 overflow-auto rounded bg-muted p-2 text-xs whitespace-pre-wrap">
                {JSON.stringify(value, null, 2)}
            </pre>
        );
    }

    return (
        <span className="block max-h-48 overflow-auto break-words whitespace-pre-wrap">
            {typeof value === 'string' ? value : JSON.stringify(value)}
        </span>
    );
}

function ChangesTable({
    fields,
    showOld,
    showNew,
}: {
    fields: AuditField[];
    showOld: boolean;
    showNew: boolean;
}) {
    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-left text-sm">
                <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                    <tr>
                        <th className="w-1/5 px-3 py-2 font-medium">Field</th>
                        {showOld && (
                            <th className="px-3 py-2 font-medium">Old value</th>
                        )}
                        {showNew && (
                            <th className="px-3 py-2 font-medium">New value</th>
                        )}
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {fields.map((field) => (
                        <tr key={field.field} className="align-top">
                            <th
                                scope="row"
                                className="px-3 py-2 font-medium capitalize"
                            >
                                {fieldName(field.field)}
                            </th>
                            {showOld && (
                                <td
                                    className={cn(
                                        'px-3 py-2',
                                        showNew &&
                                            field.in_old &&
                                            'bg-red-50/60 dark:bg-red-950/20',
                                    )}
                                >
                                    <Value
                                        value={field.old}
                                        present={field.in_old}
                                    />
                                </td>
                            )}
                            {showNew && (
                                <td
                                    className={cn(
                                        'px-3 py-2',
                                        showOld &&
                                            field.in_new &&
                                            'bg-green-50/60 dark:bg-green-950/20',
                                    )}
                                >
                                    <Value
                                        value={field.new}
                                        present={field.in_new}
                                    />
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/** Loads one entry and shows who/when/where plus the field-by-field changes. */
export default function AuditDetailView({ id }: { id: number }) {
    const hydrated = useHydrated();
    const [state, setState] = useState<
        | { status: 'loading' }
        | { status: 'error' }
        | { status: 'ready'; data: AuditDetail }
    >({ status: 'loading' });

    useEffect(() => {
        const controller = new AbortController();

        fetch(AuditLogController.show.url(id), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                return response.json() as Promise<{ data: AuditDetail }>;
            })
            .then((json) => setState({ status: 'ready', data: json.data }))
            .catch((error: unknown) => {
                if (!(error instanceof DOMException)) {
                    setState({ status: 'error' });
                }
            });

        return () => controller.abort();
    }, [id]);

    if (state.status === 'loading') {
        return (
            <div className="flex items-center gap-2 py-8 text-sm text-muted-foreground">
                <Spinner /> Loading…
            </div>
        );
    }

    if (state.status === 'error') {
        return (
            <p role="alert" className="py-8 text-sm text-destructive">
                This entry could not be loaded.
            </p>
        );
    }

    const { data } = state;
    const who = data.actor.name ?? sourceLabel(data.actor.source);

    return (
        <div className="grid gap-5">
            <dl className="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <Meta label="When">
                    {hydrated ? formatWhen(data.created_at) : ''}
                </Meta>
                <Meta label="Who">
                    {who}
                    {data.actor.email && (
                        <span className="block text-xs text-muted-foreground">
                            {data.actor.email}
                        </span>
                    )}
                    {data.actor.name === null && (
                        <span className="block text-xs text-muted-foreground">
                            Not a signed-in person
                        </span>
                    )}
                </Meta>
                <Meta label="Item">
                    <span className="capitalize">
                        {data.type.replace('_', ' ')}
                    </span>
                    {data.label && ` · ${data.label}`}
                    {data.auditable_id && (
                        <span className="block text-xs text-muted-foreground">
                            ID {data.auditable_id}
                        </span>
                    )}
                </Meta>
                <Meta label="From">
                    {data.ip_address ?? '—'}
                    {data.method && data.url && (
                        <span className="block text-xs break-all text-muted-foreground">
                            {data.method} /{data.url}
                        </span>
                    )}
                </Meta>
                {data.user_agent && (
                    <Meta label="Browser" wide>
                        <span className="text-xs break-words text-muted-foreground">
                            {data.user_agent}
                        </span>
                    </Meta>
                )}
            </dl>

            {data.fields.length > 0 ? (
                <div className="grid gap-2">
                    <h3 className="text-sm font-medium">
                        {data.has_old && data.has_new
                            ? 'What changed'
                            : data.has_new
                              ? 'Values it was created with'
                              : 'Values it had when removed'}
                    </h3>
                    {/* A column is shown only when the entry has something to put in it: a creation has
                        no "old" side and a deletion has no "new" side. */}
                    <ChangesTable
                        fields={data.fields}
                        showOld={data.has_old}
                        showNew={data.has_new}
                    />
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    No field values were recorded for this action.
                </p>
            )}
        </div>
    );
}

function Meta({
    label,
    wide = false,
    children,
}: {
    label: string;
    wide?: boolean;
    children: React.ReactNode;
}) {
    return (
        <div className={wide ? 'sm:col-span-2' : undefined}>
            <dt className="text-xs text-muted-foreground uppercase">{label}</dt>
            <dd className="mt-0.5">{children}</dd>
        </div>
    );
}
