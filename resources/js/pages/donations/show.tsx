import { Form, Head, Link } from '@inertiajs/react';
import { ExternalLink, Send } from 'lucide-react';
import DonationController from '@/actions/App/Http/Controllers/Admin/DonationController';
import DonationStatusBadge from '@/components/donations/donation-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/date';
import { formatCents } from '@/lib/money';
import type { DonationDetail } from '@/types';

function Row({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="m-0 break-words">{children}</dd>
        </>
    );
}

export default function DonationShow({
    donation,
}: {
    donation: DonationDetail;
}) {
    return (
        <>
            <Head title={`Donation from ${donation.donor}`} />

            <div className="mx-auto w-full max-w-5xl space-y-6 p-4">
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-3">
                        <h2 className="text-xl font-semibold tracking-tight">
                            {formatCents(donation.amount_cents)} from{' '}
                            {donation.donor}
                        </h2>
                        <DonationStatusBadge status={donation.status} />
                        {donation.is_test && (
                            <Badge variant="outline">Test donation</Badge>
                        )}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {formatDateTime(donation.date)} ·{' '}
                        {donation.event ?? 'General donation'}
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Donor</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="m-0 grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                                <Row label="Name">{donation.donor}</Row>
                                <Row label="Email">
                                    <a
                                        href={`mailto:${donation.email}`}
                                        className="underline underline-offset-4"
                                    >
                                        {donation.email}
                                    </a>
                                </Row>
                                {donation.phone && (
                                    <Row label="Phone">{donation.phone}</Row>
                                )}
                                <Row label="Address">
                                    {donation.address.map((line) => (
                                        <span key={line} className="block">
                                            {line}
                                        </span>
                                    ))}
                                </Row>
                                <Row label="Agreed to privacy terms">
                                    {formatDateTime(donation.consent_at)}
                                </Row>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Payment</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="m-0 grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                                <Row label="Reference">
                                    <code className="text-xs">
                                        {donation.reference}
                                    </code>
                                </Row>
                                <Row label="Provider">{donation.gateway}</Row>
                                {donation.card && (
                                    <Row label="Card">{donation.card}</Row>
                                )}
                                {donation.transaction_id && (
                                    <Row label="Transaction">
                                        <code className="text-xs">
                                            {donation.transaction_id}
                                        </code>
                                    </Row>
                                )}
                                {donation.response && (
                                    <Row label="Provider answer">
                                        {donation.response}
                                    </Row>
                                )}
                                {donation.failure_reason && (
                                    <Row label="Why it failed">
                                        {donation.failure_reason}
                                    </Row>
                                )}
                                {donation.refunded_cents > 0 && (
                                    <Row label="Refunded">
                                        {formatCents(donation.refunded_cents)}
                                    </Row>
                                )}
                            </dl>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Tax receipt</CardTitle>
                        <CardDescription>
                            {donation.receipt_number
                                ? `Receipt ${donation.receipt_number}, issued ${formatDateTime(donation.receipt_issued_at)}.`
                                : 'A receipt is issued when the payment succeeds.'}
                        </CardDescription>
                    </CardHeader>
                    {donation.receipt_number && (
                        <CardContent className="space-y-3">
                            <p className="m-0 text-sm text-muted-foreground">
                                {donation.receipt_sent_at
                                    ? `Emailed to ${donation.email} on ${formatDateTime(donation.receipt_sent_at)}.`
                                    : 'Not emailed yet (it is queued, or email is not set up on the server).'}
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Button asChild variant="outline">
                                    <a
                                        href={DonationController.receipt.url(
                                            donation.id,
                                        )}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <ExternalLink /> Preview receipt
                                    </a>
                                </Button>
                                {donation.can_resend && (
                                    <Form
                                        {...DonationController.resend.form(
                                            donation.id,
                                        )}
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="secondary"
                                                disabled={processing}
                                            >
                                                {processing ? (
                                                    <Spinner />
                                                ) : (
                                                    <Send />
                                                )}{' '}
                                                Send again
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            </div>
                        </CardContent>
                    )}
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Activity</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ol className="m-0 grid list-none gap-2 p-0 text-sm">
                            {donation.trail.map((entry) => (
                                <li key={entry.id} className="flex gap-4">
                                    <span className="w-44 shrink-0 text-muted-foreground tabular-nums">
                                        {formatDateTime(entry.at)}
                                    </span>
                                    <span>
                                        {entry.type.replaceAll('_', ' ')}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    </CardContent>
                </Card>

                <p>
                    <Link
                        href={DonationController.index()}
                        className="text-sm underline underline-offset-4"
                    >
                        ← Back to donations
                    </Link>
                </p>
            </div>
        </>
    );
}

DonationShow.layout = {
    breadcrumbs: [
        { title: 'Donations', href: DonationController.index() },
        { title: 'Donation', href: DonationController.index() },
    ],
};
