import { Form, Head, Link } from '@inertiajs/react';
import { HeartHandshake, Plus, Search, Star } from 'lucide-react';
import EventController from '@/actions/App/Http/Controllers/Admin/EventController';
import EventStateBadge from '@/components/events/event-state-badge';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
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
import { usePermissions } from '@/hooks/use-permissions';
import { cn } from '@/lib/utils';
import type { EventCounts, EventFilter, EventRow, Paginated } from '@/types';

type Props = {
    events: Paginated<EventRow>;
    filters: { status: EventFilter; search: string };
    counts: EventCounts;
    can: { forceDelete: boolean };
};

const TABS: { key: EventFilter; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'upcoming', label: 'Upcoming' },
    { key: 'past', label: 'Past' },
    { key: 'draft', label: 'Drafts' },
    { key: 'trashed', label: 'Trash' },
];

const EMPTY_TEXT: Record<EventFilter, string> = {
    all: 'No events yet.',
    upcoming: 'No upcoming events.',
    past: 'No past events.',
    draft: 'No drafts.',
    trashed: 'The trash is empty.',
};

/** "Sat, Nov 14, 2026, 6:00 PM" in the event's own time zone (what visitors will read). */
function when(event: EventRow): string {
    return new Intl.DateTimeFormat('en-CA', {
        dateStyle: 'medium',
        ...(event.is_all_day ? {} : { timeStyle: 'short' as const }),
        timeZone: event.timezone,
    }).format(new Date(event.starts_at));
}

function DeleteForeverButton({ event }: { event: EventRow }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="destructive" size="sm">
                    Delete permanently
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete “{event.title}” permanently?</DialogTitle>
                <DialogDescription>
                    This removes the event and its old web addresses. Donations
                    made for it keep their record. It cannot be undone.
                </DialogDescription>
                <Form
                    {...EventController.forceDelete.form(event.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                type="submit"
                                disabled={processing}
                            >
                                Delete permanently
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function EventsIndex({ events, filters, counts, can }: Props) {
    const perms = usePermissions();
    const tabHref = (status: EventFilter) =>
        EventController.index({
            query: {
                status: status === 'all' ? undefined : status,
                search: filters.search || undefined,
            },
        });

    return (
        <>
            <Head title="Events" />

            <div className="mx-auto w-full max-w-6xl space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Events"
                        description="Fundraisers and community events shown on the Upcoming Events page."
                    />
                    {perms.can('events.create') && (
                        <Button asChild>
                            <Link href={EventController.create()}>
                                <Plus /> New event
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <nav
                        aria-label="Filter events"
                        className="flex flex-wrap gap-1"
                    >
                        {TABS.map((tab) => {
                            const active = filters.status === tab.key;

                            return (
                                <Link
                                    key={tab.key}
                                    href={tabHref(tab.key)}
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'rounded-md px-3 py-1.5 text-sm transition-colors',
                                        active
                                            ? 'bg-secondary font-medium text-secondary-foreground'
                                            : 'text-muted-foreground hover:bg-muted',
                                    )}
                                >
                                    {tab.label}{' '}
                                    <span className="text-xs tabular-nums opacity-70">
                                        {counts[tab.key]}
                                    </span>
                                </Link>
                            );
                        })}
                    </nav>

                    <Form
                        {...EventController.index.form()}
                        className="flex items-center gap-2"
                        role="search"
                    >
                        {filters.status !== 'all' && (
                            <input
                                type="hidden"
                                name="status"
                                value={filters.status}
                            />
                        )}
                        <Input
                            type="search"
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Search title, address or city"
                            aria-label="Search events"
                            className="w-64"
                        />
                        <Button type="submit" variant="secondary">
                            <Search /> Search
                        </Button>
                    </Form>
                </div>

                {events.data.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-10 text-center">
                        <p className="text-muted-foreground">
                            {filters.search
                                ? `No events match “${filters.search}”.`
                                : EMPTY_TEXT[filters.status]}
                        </p>
                        {filters.status === 'all' &&
                            !filters.search &&
                            perms.can('events.create') && (
                                <Button asChild className="mt-4">
                                    <Link href={EventController.create()}>
                                        <Plus /> Add the first event
                                    </Link>
                                </Button>
                            )}
                    </div>
                ) : (
                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-16">
                                        <span className="sr-only">Image</span>
                                    </TableHead>
                                    <TableHead>Event</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>When</TableHead>
                                    <TableHead>Where</TableHead>
                                    <TableHead className="text-right">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {events.data.map((event) => (
                                    <TableRow key={event.id}>
                                        <TableCell>
                                            {event.image_url ? (
                                                <img
                                                    src={event.image_url}
                                                    alt=""
                                                    className="size-12 rounded object-cover"
                                                />
                                            ) : (
                                                <div
                                                    className="size-12 rounded bg-muted"
                                                    aria-hidden="true"
                                                />
                                            )}
                                        </TableCell>
                                        <TableCell className="max-w-md">
                                            {event.state === 'trashed' ? (
                                                <span className="font-medium">
                                                    {event.title}
                                                </span>
                                            ) : (
                                                <Link
                                                    href={EventController.edit(
                                                        event.id,
                                                    )}
                                                    className="font-medium underline-offset-4 hover:underline"
                                                >
                                                    {event.title}
                                                </Link>
                                            )}
                                            <div className="truncate text-xs text-muted-foreground">
                                                /{event.slug}
                                            </div>
                                            <div className="mt-1 flex flex-wrap gap-1">
                                                {event.is_featured && (
                                                    <Badge variant="outline">
                                                        <Star /> Featured
                                                    </Badge>
                                                )}
                                                {event.show_in_donation_form && (
                                                    <Badge variant="outline">
                                                        <HeartHandshake /> In
                                                        donation form
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <EventStateBadge
                                                state={event.state}
                                            />
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-muted-foreground">
                                            {when(event)}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {event.place ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {event.state === 'trashed' ? (
                                                <div className="flex justify-end gap-2">
                                                    <Form
                                                        {...EventController.restore.form(
                                                            event.id,
                                                        )}
                                                        options={{
                                                            preserveScroll: true,
                                                        }}
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                type="submit"
                                                                variant="outline"
                                                                size="sm"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                Restore
                                                            </Button>
                                                        )}
                                                    </Form>
                                                    {can.forceDelete && (
                                                        <DeleteForeverButton
                                                            event={event}
                                                        />
                                                    )}
                                                </div>
                                            ) : (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={EventController.edit(
                                                            event.id,
                                                        )}
                                                    >
                                                        Edit
                                                    </Link>
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {events.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Showing {events.from}–{events.to} of {events.total}
                        </span>
                        <div className="flex gap-2">
                            {events.prev_page_url && (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={events.prev_page_url}>
                                        Previous
                                    </Link>
                                </Button>
                            )}
                            {events.next_page_url && (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={events.next_page_url}>
                                        Next
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

EventsIndex.layout = {
    breadcrumbs: [{ title: 'Events', href: EventController.index() }],
};
