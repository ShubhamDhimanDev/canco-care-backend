import { Form, Head, Link } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import DonationController from '@/actions/App/Http/Controllers/Admin/DonationController';
import DonationStatusBadge from '@/components/donations/donation-status-badge';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
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
import { formatCents } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { DonationRow, DonationView, Paginated } from '@/types';

type Props = {
    donations: Paginated<DonationRow>;
    filters: {
        view: DonationView;
        search: string;
        from: string;
        to: string;
        mode: 'all' | 'real' | 'test';
    };
    counts: Record<DonationView, number>;
    totals: { gifts: number; cents: number };
};

const TABS: { key: DonationView; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'succeeded', label: 'Succeeded' },
    { key: 'pending', label: 'Pending' },
    { key: 'failed', label: 'Failed' },
    { key: 'refunded', label: 'Refunded' },
];

export default function DonationsIndex({
    donations,
    filters,
    counts,
    totals,
}: Props) {
    const query = (view: DonationView) => ({
        view: view === 'all' ? undefined : view,
        search: filters.search || undefined,
        from: filters.from || undefined,
        to: filters.to || undefined,
        mode: filters.mode === 'all' ? undefined : filters.mode,
    });

    return (
        <>
            <Head title="Donations" />

            <div className="mx-auto w-full max-w-6xl space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Donations"
                        description="Every gift made through the website. Card details are never stored here."
                    />
                    <Button asChild variant="outline">
                        <a
                            href={DonationController.export.url({
                                query: query(filters.view),
                            })}
                        >
                            <Download /> Export CSV
                        </a>
                    </Button>
                </div>

                <dl className="m-0 grid gap-4 sm:grid-cols-2">
                    <div className="rounded-lg border p-4">
                        <dt className="text-sm text-muted-foreground">
                            Received (these filters)
                        </dt>
                        <dd className="m-0 text-2xl font-semibold tabular-nums">
                            {formatCents(totals.cents)}
                        </dd>
                    </div>
                    <div className="rounded-lg border p-4">
                        <dt className="text-sm text-muted-foreground">Gifts</dt>
                        <dd className="m-0 text-2xl font-semibold tabular-nums">
                            {totals.gifts}
                        </dd>
                    </div>
                </dl>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <nav
                        aria-label="Filter donations"
                        className="flex flex-wrap gap-1"
                    >
                        {TABS.map((tab) => {
                            const active = filters.view === tab.key;

                            return (
                                <Link
                                    key={tab.key}
                                    href={DonationController.index({
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
                                    <span className="text-xs tabular-nums opacity-70">
                                        {counts[tab.key]}
                                    </span>
                                </Link>
                            );
                        })}
                    </nav>

                    <Form
                        {...DonationController.index.form()}
                        className="flex flex-wrap items-center gap-2"
                        role="search"
                    >
                        {filters.view !== 'all' && (
                            <input
                                type="hidden"
                                name="view"
                                value={filters.view}
                            />
                        )}
                        <label className="flex items-center gap-1 text-sm">
                            <span className="sr-only sm:not-sr-only">From</span>
                            <Input
                                type="date"
                                name="from"
                                defaultValue={filters.from}
                                className="w-40"
                            />
                        </label>
                        <label className="flex items-center gap-1 text-sm">
                            <span className="sr-only sm:not-sr-only">To</span>
                            <Input
                                type="date"
                                name="to"
                                defaultValue={filters.to}
                                className="w-40"
                            />
                        </label>
                        <select
                            name="mode"
                            defaultValue={filters.mode}
                            aria-label="Real or test donations"
                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option value="all">Real and test</option>
                            <option value="real">Real only</option>
                            <option value="test">Test only</option>
                        </select>
                        <Input
                            type="search"
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Name, email, receipt #"
                            aria-label="Search donations"
                            className="w-52"
                        />
                        <Button type="submit" variant="secondary">
                            <Search /> Filter
                        </Button>
                    </Form>
                </div>

                {donations.data.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-10 text-center text-muted-foreground">
                        {filters.search || filters.from || filters.to
                            ? 'No donations match those filters.'
                            : 'No donations yet.'}
                    </div>
                ) : (
                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Donor</TableHead>
                                    <TableHead>Event</TableHead>
                                    <TableHead className="text-right">
                                        Amount
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Receipt</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {donations.data.map((donation) => (
                                    <TableRow key={donation.id}>
                                        <TableCell className="whitespace-nowrap text-muted-foreground">
                                            {formatDateTime(donation.date)}
                                        </TableCell>
                                        <TableCell>
                                            <Link
                                                href={DonationController.show(
                                                    donation.id,
                                                )}
                                                className="font-medium underline-offset-4 hover:underline"
                                            >
                                                {donation.donor}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {donation.short_reference}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {donation.event ?? 'General'}
                                        </TableCell>
                                        <TableCell className="text-right font-medium tabular-nums">
                                            {formatCents(donation.amount_cents)}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                <DonationStatusBadge
                                                    status={donation.status}
                                                />
                                                {donation.is_test && (
                                                    <Badge variant="outline">
                                                        Test
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-muted-foreground">
                                            {donation.receipt_number ?? '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {donations.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Showing {donations.from}–{donations.to} of{' '}
                            {donations.total}
                        </span>
                        <div className="flex gap-2">
                            {donations.prev_page_url && (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={donations.prev_page_url}>
                                        Previous
                                    </Link>
                                </Button>
                            )}
                            {donations.next_page_url && (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={donations.next_page_url}>
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

DonationsIndex.layout = {
    breadcrumbs: [{ title: 'Donations', href: DonationController.index() }],
};
