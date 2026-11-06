import { Form, Head, Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import AuditLogController from '@/actions/App/Http/Controllers/Admin/AuditLogController';
import AuditDetailView from '@/components/audit/audit-detail';
import {
    eventTone,
    formatWhen,
    sourceLabel,
} from '@/components/audit/audit-labels';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useHydrated } from '@/hooks/use-hydrated';
import { cn } from '@/lib/utils';
import type { AuditFilters, AuditRow, Paginated } from '@/types';

type Props = {
    logs: Paginated<AuditRow>;
    filters: AuditFilters;
    users: { id: number; name: string }[];
    events: Record<string, string>;
    types: Record<string, string>;
};

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm';

export default function AuditLogsIndex({
    logs,
    filters,
    users,
    events,
    types,
}: Props) {
    const hydrated = useHydrated();
    const [open, setOpen] = useState<AuditRow | null>(null);
    const filtered = Object.values(filters).some((value) => value !== '');

    return (
        <>
            <Head title="Activity log" />

            <div className="mx-auto w-full max-w-7xl space-y-6 p-4">
                <Heading
                    title="Activity log"
                    description="Who added, changed or deleted what, and when. Entries can't be edited or removed here; the oldest are cleared automatically after a year."
                />

                <Form
                    {...AuditLogController.index.form()}
                    className="flex flex-wrap items-end gap-2"
                    role="search"
                >
                    <label className="grid gap-1 text-xs text-muted-foreground">
                        Person
                        <select
                            name="user"
                            defaultValue={filters.user}
                            className={selectClass}
                        >
                            <option value="">Everyone</option>
                            <option value="none">Not a signed-in person</option>
                            {users.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-1 text-xs text-muted-foreground">
                        Action
                        <select
                            name="event"
                            defaultValue={filters.event}
                            className={selectClass}
                        >
                            <option value="">Any action</option>
                            {Object.entries(events).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-1 text-xs text-muted-foreground">
                        About
                        <select
                            name="type"
                            defaultValue={filters.type}
                            className={selectClass}
                        >
                            <option value="">Anything</option>
                            {Object.entries(types).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-1 text-xs text-muted-foreground">
                        From
                        <Input
                            type="date"
                            name="from"
                            defaultValue={filters.from}
                            className="w-40"
                        />
                    </label>
                    <label className="grid gap-1 text-xs text-muted-foreground">
                        To
                        <Input
                            type="date"
                            name="to"
                            defaultValue={filters.to}
                            className="w-40"
                        />
                    </label>
                    <label className="grid gap-1 text-xs text-muted-foreground">
                        Search
                        <Input
                            type="search"
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Name, title, email, IP"
                            className="w-52"
                        />
                    </label>
                    <Button type="submit" variant="secondary">
                        <Search /> Filter
                    </Button>
                    {filtered && (
                        <Button asChild variant="ghost">
                            <Link href={AuditLogController.index()}>Clear</Link>
                        </Button>
                    )}
                </Form>

                {logs.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                        {filtered
                            ? 'Nothing matches these filters.'
                            : 'Nothing has been recorded yet.'}
                    </div>
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>When</TableHead>
                                    <TableHead>Who</TableHead>
                                    <TableHead>Action</TableHead>
                                    <TableHead>What</TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Fields
                                    </TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        From
                                    </TableHead>
                                    <TableHead>
                                        <span className="sr-only">Details</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {logs.data.map((log) => (
                                    <TableRow key={log.id}>
                                        <TableCell className="text-sm whitespace-nowrap">
                                            {hydrated
                                                ? formatWhen(log.created_at)
                                                : ''}
                                        </TableCell>
                                        <TableCell>
                                            <div className="text-sm font-medium">
                                                {log.actor.name ??
                                                    sourceLabel(
                                                        log.actor.source,
                                                    )}
                                            </div>
                                            {log.actor.email && (
                                                <div className="text-xs text-muted-foreground">
                                                    {log.actor.email}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <span
                                                className={cn(
                                                    'inline-block rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                                                    eventTone(log.event),
                                                )}
                                            >
                                                {events[log.event] ?? log.event}
                                            </span>
                                        </TableCell>
                                        <TableCell className="max-w-64">
                                            <div className="text-xs text-muted-foreground">
                                                {types[log.type] ?? log.type}
                                            </div>
                                            <div className="truncate text-sm">
                                                {log.label ?? '—'}
                                            </div>
                                        </TableCell>
                                        <TableCell className="hidden max-w-56 text-xs text-muted-foreground lg:table-cell">
                                            {log.changed.join(', ')}
                                            {log.changed_count >
                                                log.changed.length &&
                                                ` +${log.changed_count - log.changed.length} more`}
                                        </TableCell>
                                        <TableCell className="hidden text-xs text-muted-foreground md:table-cell">
                                            {log.ip_address ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => setOpen(log)}
                                            >
                                                Details
                                                <span className="sr-only">
                                                    {' '}
                                                    of entry {log.id}
                                                </span>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {logs.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Showing {logs.from}–{logs.to} of {logs.total}
                        </span>
                        <div className="flex gap-2">
                            {logs.prev_page_url && (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={logs.prev_page_url}>
                                        Previous
                                    </Link>
                                </Button>
                            )}
                            <span className="self-center tabular-nums">
                                Page {logs.current_page} of {logs.last_page}
                            </span>
                            {logs.next_page_url && (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={logs.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>

            <Dialog
                open={open !== null}
                onOpenChange={(next) => !next && setOpen(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
                    {open && (
                        <>
                            <DialogTitle>
                                {events[open.event] ?? open.event} ·{' '}
                                {types[open.type] ?? open.type}
                            </DialogTitle>
                            <DialogDescription>
                                Entry #{open.id}
                            </DialogDescription>
                            <AuditDetailView key={open.id} id={open.id} />
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

AuditLogsIndex.layout = {
    breadcrumbs: [{ title: 'Activity log', href: AuditLogController.index() }],
};
