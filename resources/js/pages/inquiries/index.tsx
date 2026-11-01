import { Form, Head, Link } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import InquiryController from '@/actions/App/Http/Controllers/Admin/InquiryController';
import Heading from '@/components/heading';
import InquiryStatusBadge from '@/components/inquiries/inquiry-status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/date';
import { cn } from '@/lib/utils';
import type {
    InquiryCounts,
    InquiryRow,
    InquiryView,
    Paginated,
} from '@/types';

type Props = {
    inquiries: Paginated<InquiryRow>;
    filters: { view: InquiryView; type: string; search: string };
    counts: InquiryCounts;
    types: { slug: string; name: string }[];
};

const TABS: { key: InquiryView; label: string }[] = [
    { key: 'inbox', label: 'Inbox' },
    { key: 'new', label: 'New' },
    { key: 'replied', label: 'Replied' },
    { key: 'archived', label: 'Archived' },
    { key: 'spam', label: 'Spam' },
    { key: 'all', label: 'All' },
];

const EMPTY_TEXT: Record<InquiryView, string> = {
    inbox: 'Nothing waiting. New messages from the contact form appear here.',
    new: 'No new inquiries.',
    replied: 'No replied inquiries.',
    archived: 'Nothing archived.',
    spam: 'No spam.',
    all: 'No inquiries yet.',
};

export default function InquiriesIndex({
    inquiries,
    filters,
    counts,
    types,
}: Props) {
    const query = (view: InquiryView) => ({
        view: view === 'inbox' ? undefined : view,
        type: filters.type || undefined,
        search: filters.search || undefined,
    });

    return (
        <>
            <Head title="Inquiries" />

            <div className="mx-auto w-full max-w-6xl space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Inquiries"
                        description="Messages sent through the contact form. Each goes to the email address set for its topic."
                    />
                    <Button asChild variant="outline">
                        <a
                            href={InquiryController.export.url({
                                query: query(filters.view),
                            })}
                        >
                            <Download /> Export CSV
                        </a>
                    </Button>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <nav
                        aria-label="Filter inquiries"
                        className="flex flex-wrap gap-1"
                    >
                        {TABS.map((tab) => {
                            const active = filters.view === tab.key;

                            return (
                                <Link
                                    key={tab.key}
                                    href={InquiryController.index({
                                        query: query(tab.key),
                                    })}
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'rounded-md px-3 py-1.5 text-sm transition-colors',
                                        active
                                            ? 'bg-secondary font-medium text-secondary-foreground'
                                            : 'text-muted-foreground hover:bg-muted',
                                    )}
                                >
                                    {tab.label}{' '}
                                    <span
                                        className={cn(
                                            'text-xs tabular-nums',
                                            tab.key === 'new' && counts.new > 0
                                                ? 'font-semibold text-orange-600'
                                                : 'opacity-70',
                                        )}
                                    >
                                        {counts[tab.key]}
                                    </span>
                                </Link>
                            );
                        })}
                    </nav>

                    <Form
                        {...InquiryController.index.form()}
                        className="flex flex-wrap items-center gap-2"
                        role="search"
                    >
                        {filters.view !== 'inbox' && (
                            <input
                                type="hidden"
                                name="view"
                                value={filters.view}
                            />
                        )}
                        {types.length > 0 && (
                            <select
                                name="type"
                                defaultValue={filters.type}
                                aria-label="Filter by topic"
                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                            >
                                <option value="">All topics</option>
                                {types.map((type) => (
                                    <option key={type.slug} value={type.slug}>
                                        {type.name}
                                    </option>
                                ))}
                            </select>
                        )}
                        <Input
                            type="search"
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Search name, email or message"
                            aria-label="Search inquiries"
                            className="w-64"
                        />
                        <Button type="submit" variant="secondary">
                            <Search /> Search
                        </Button>
                    </Form>
                </div>

                {inquiries.data.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-10 text-center text-muted-foreground">
                        {filters.search || filters.type
                            ? 'No inquiries match those filters.'
                            : EMPTY_TEXT[filters.view]}
                    </div>
                ) : (
                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>From</TableHead>
                                    <TableHead>Topic</TableHead>
                                    <TableHead>Message</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Received</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {inquiries.data.map((inquiry) => (
                                    <TableRow
                                        key={inquiry.id}
                                        className={cn(
                                            inquiry.status === 'new' &&
                                                'bg-orange-50/60 dark:bg-orange-950/20',
                                        )}
                                    >
                                        <TableCell className="whitespace-nowrap">
                                            <Link
                                                href={InquiryController.show(
                                                    inquiry.id,
                                                )}
                                                className={cn(
                                                    'underline-offset-4 hover:underline',
                                                    inquiry.status === 'new'
                                                        ? 'font-semibold'
                                                        : 'font-medium',
                                                )}
                                            >
                                                {inquiry.name}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {inquiry.email}
                                            </div>
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-muted-foreground">
                                            {inquiry.type ?? '—'}
                                        </TableCell>
                                        <TableCell className="max-w-sm truncate text-muted-foreground">
                                            {inquiry.snippet}
                                        </TableCell>
                                        <TableCell>
                                            <InquiryStatusBadge
                                                status={inquiry.status}
                                            />
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-muted-foreground">
                                            {formatDateTime(
                                                inquiry.received_at,
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {inquiries.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Showing {inquiries.from}–{inquiries.to} of{' '}
                            {inquiries.total}
                        </span>
                        <div className="flex gap-2">
                            {inquiries.prev_page_url && (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={inquiries.prev_page_url}>
                                        Previous
                                    </Link>
                                </Button>
                            )}
                            {inquiries.next_page_url && (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={inquiries.next_page_url}>
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

InquiriesIndex.layout = {
    breadcrumbs: [{ title: 'Inquiries', href: InquiryController.index() }],
};
