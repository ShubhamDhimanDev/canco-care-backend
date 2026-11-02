import { Form, Head, Link } from '@inertiajs/react';
import { Mail, Phone } from 'lucide-react';
import InquiryController from '@/actions/App/Http/Controllers/Admin/InquiryController';
import InputError from '@/components/input-error';
import InquiryStatusBadge from '@/components/inquiries/inquiry-status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/date';
import type { InquiryDetail } from '@/types';

type Props = {
    inquiry: InquiryDetail;
    statuses: { value: string; label: string }[];
    staff: { id: number; name: string }[];
};

const UNASSIGNED = 'none';

export default function InquiryShow({ inquiry, statuses, staff }: Props) {
    const subject = encodeURIComponent(
        `Re: your message to Canco Cares (${inquiry.reference})`,
    );

    return (
        <>
            <Head title={`Inquiry from ${inquiry.name}`} />

            <div className="mx-auto w-full max-w-5xl space-y-6 p-4">
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-3">
                        <h2 className="text-xl font-semibold tracking-tight">
                            {inquiry.name}
                        </h2>
                        <InquiryStatusBadge status={inquiry.status} />
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Received {formatDateTime(inquiry.received_at)} ·
                        reference {inquiry.reference}
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Message
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Plain text only: whitespace is kept, nothing is ever rendered as HTML. */}
                                <p className="m-0 text-sm leading-6 whitespace-pre-wrap">
                                    {inquiry.message}
                                </p>
                                <div className="flex flex-wrap gap-2 border-t pt-4">
                                    <Button asChild>
                                        <a
                                            href={`mailto:${inquiry.email}?subject=${subject}`}
                                        >
                                            <Mail /> Reply by email
                                        </a>
                                    </Button>
                                    <Button asChild variant="outline">
                                        <a href={`tel:${inquiry.phone_raw}`}>
                                            <Phone /> {inquiry.phone}
                                        </a>
                                    </Button>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    After you have answered, set the status to
                                    “Replied” so the team can see it is done.
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Sender
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="m-0 grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                                    <dt className="text-muted-foreground">
                                        Email
                                    </dt>
                                    <dd className="m-0">{inquiry.email}</dd>
                                    <dt className="text-muted-foreground">
                                        Phone
                                    </dt>
                                    <dd className="m-0">{inquiry.phone}</dd>
                                    <dt className="text-muted-foreground">
                                        Topic
                                    </dt>
                                    <dd className="m-0">
                                        {inquiry.type ?? 'Not chosen'}
                                    </dd>
                                    <dt className="text-muted-foreground">
                                        Sent to
                                    </dt>
                                    <dd className="m-0">
                                        {inquiry.routed_to ?? (
                                            <span className="text-amber-700 dark:text-amber-400">
                                                Nobody — no address was set for
                                                this topic
                                            </span>
                                        )}
                                    </dd>
                                    {inquiry.source_url && (
                                        <>
                                            <dt className="text-muted-foreground">
                                                Page
                                            </dt>
                                            <dd className="m-0 break-all">
                                                {inquiry.source_url}
                                            </dd>
                                        </>
                                    )}
                                    {inquiry.replied_at && (
                                        <>
                                            <dt className="text-muted-foreground">
                                                Replied
                                            </dt>
                                            <dd className="m-0">
                                                {formatDateTime(
                                                    inquiry.replied_at,
                                                )}
                                            </dd>
                                        </>
                                    )}
                                </dl>
                            </CardContent>
                        </Card>
                    </div>

                    <aside>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Follow-up
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    {...InquiryController.update.form(
                                        inquiry.id,
                                    )}
                                    options={{ preserveScroll: true }}
                                    className="grid gap-4"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="status">
                                                    Status
                                                </Label>
                                                <Select
                                                    name="status"
                                                    defaultValue={
                                                        inquiry.status
                                                    }
                                                >
                                                    <SelectTrigger
                                                        id="status"
                                                        className="w-full"
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {statuses.map(
                                                            (status) => (
                                                                <SelectItem
                                                                    key={
                                                                        status.value
                                                                    }
                                                                    value={
                                                                        status.value
                                                                    }
                                                                >
                                                                    {
                                                                        status.label
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={errors.status}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="assigned_to">
                                                    Assigned to
                                                </Label>
                                                <Select
                                                    name="assigned_to"
                                                    defaultValue={
                                                        inquiry.assigned_to
                                                            ? String(
                                                                  inquiry.assigned_to,
                                                              )
                                                            : UNASSIGNED
                                                    }
                                                >
                                                    <SelectTrigger
                                                        id="assigned_to"
                                                        className="w-full"
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            value={UNASSIGNED}
                                                        >
                                                            Nobody
                                                        </SelectItem>
                                                        {staff.map((person) => (
                                                            <SelectItem
                                                                key={person.id}
                                                                value={String(
                                                                    person.id,
                                                                )}
                                                            >
                                                                {person.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={errors.assigned_to}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="admin_notes">
                                                    Private notes
                                                </Label>
                                                <Textarea
                                                    id="admin_notes"
                                                    name="admin_notes"
                                                    defaultValue={
                                                        inquiry.admin_notes
                                                    }
                                                    rows={5}
                                                    maxLength={5000}
                                                    placeholder="Only the team sees this."
                                                />
                                                <InputError
                                                    message={errors.admin_notes}
                                                />
                                            </div>

                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />} Save
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>

                        <Dialog>
                            <DialogTrigger asChild>
                                <Button
                                    variant="ghost"
                                    className="mt-4 w-full text-destructive-foreground"
                                >
                                    Delete this inquiry
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>
                                    Delete the inquiry from {inquiry.name}?
                                </DialogTitle>
                                <DialogDescription>
                                    It is removed from the inbox. Use “Archived”
                                    instead if you may want to find it later.
                                </DialogDescription>
                                <Form
                                    {...InquiryController.destroy.form(
                                        inquiry.id,
                                    )}
                                >
                                    {({ processing }) => (
                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                >
                                                    Cancel
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                variant="destructive"
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Delete
                                            </Button>
                                        </DialogFooter>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </aside>
                </div>

                <p>
                    <Link
                        href={InquiryController.index()}
                        className="text-sm underline underline-offset-4"
                    >
                        ← Back to the inbox
                    </Link>
                </p>
            </div>
        </>
    );
}

InquiryShow.layout = {
    breadcrumbs: [
        { title: 'Inquiries', href: InquiryController.index() },
        { title: 'Inquiry', href: InquiryController.index() },
    ],
};
