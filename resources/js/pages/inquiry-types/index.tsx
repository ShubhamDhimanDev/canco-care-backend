import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import InquiryTypeController from '@/actions/App/Http/Controllers/Admin/InquiryTypeController';
import CheckField from '@/components/check-field';
import FormField from '@/components/form-field';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import type { InquiryTypeRow } from '@/types';

type Props = {
    types: InquiryTypeRow[];
    fallbackEmails: string[];
};

/** Add (no `type`) or edit a topic. */
function TypeDialog({ type }: { type?: InquiryTypeRow }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {type ? (
                    <Button variant="outline" size="sm">
                        <Pencil /> Edit
                        <span className="sr-only"> {type.name}</span>
                    </Button>
                ) : (
                    <Button>
                        <Plus /> Add a topic
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {type ? `Edit “${type.name}”` : 'Add a topic'}
                </DialogTitle>
                <DialogDescription>
                    {type
                        ? 'Changes apply to new inquiries straight away.'
                        : 'It appears in the contact form’s dropdown, and every inquiry about it is emailed to the address you enter.'}
                </DialogDescription>
                <Form
                    {...(type
                        ? InquiryTypeController.update.form(type.id)
                        : InquiryTypeController.store.form())}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <FormField
                                name="name"
                                label="Topic name"
                                defaultValue={type?.name}
                                placeholder="e.g. Partnerships"
                                maxLength={80}
                                required
                                error={errors.name}
                            />
                            <FormField
                                name="email"
                                label="Send these inquiries to"
                                type="email"
                                defaultValue={type?.email}
                                placeholder="partners@cancocares.ca"
                                maxLength={190}
                                required
                                autoComplete="off"
                                error={errors.email}
                            />
                            <FormField
                                name="sort_order"
                                label="Order in the dropdown"
                                type="number"
                                min={0}
                                max={65000}
                                defaultValue={type?.sort_order}
                                help="Lower numbers come first. Leave empty to add it at the end."
                                error={errors.sort_order}
                            />
                            <CheckField
                                name="is_active"
                                label="Show in the contact form"
                                description="Untick to hide the topic without deleting it."
                                defaultChecked={type?.is_active ?? true}
                            />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />} Save
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteDialog({ type }: { type: InquiryTypeRow }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm" className="text-destructive">
                    Delete
                    <span className="sr-only"> {type.name}</span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete “{type.name}”?</DialogTitle>
                <DialogDescription>
                    It disappears from the contact form. The{' '}
                    {type.inquiries_count} past inquir
                    {type.inquiries_count === 1 ? 'y' : 'ies'} about it are kept
                    and still show this topic name. To hide it temporarily, edit
                    it and untick “Show in the contact form” instead.
                </DialogDescription>
                <Form
                    {...InquiryTypeController.destroy.form(type.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                type="submit"
                                disabled={processing}
                            >
                                Delete topic
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/** One address per line; submitted as `emails[]`. */
function FallbackForm({ emails }: { emails: string[] }) {
    const [text, setText] = useState(emails.join('\n'));
    const lines = text
        .split(/[\n,;]+/)
        .map((line) => line.trim())
        .filter(Boolean);

    return (
        <Form
            {...InquiryTypeController.updateFallback.form()}
            options={{ preserveScroll: true }}
            className="grid gap-3"
        >
            {({ errors, processing }) => (
                <>
                    {lines.map((email) => (
                        <input
                            key={email}
                            type="hidden"
                            name="emails[]"
                            value={email}
                        />
                    ))}
                    <Label htmlFor="fallback">Fallback addresses</Label>
                    <Textarea
                        id="fallback"
                        value={text}
                        onChange={(event) => setText(event.target.value)}
                        rows={3}
                        placeholder="one address per line"
                        aria-describedby="fallback-help"
                    />
                    <p
                        id="fallback-help"
                        className="text-xs text-muted-foreground"
                    >
                        Used when a topic has no address, or when a visitor
                        sends a message without choosing a topic.
                    </p>
                    {Object.entries(errors).map(([key, message]) => (
                        <InputError key={key} message={message} />
                    ))}
                    <div>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />} Save addresses
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

export default function InquiryTypesIndex({ types, fallbackEmails }: Props) {
    return (
        <>
            <Head title="Inquiry topics" />

            <div className="mx-auto w-full max-w-4xl space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Inquiry topics"
                        description="The dropdown on the contact form. Each topic has the email address its inquiries are sent to."
                    />
                    <TypeDialog />
                </div>

                {types.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-10 text-center text-muted-foreground">
                        No topics yet. Without any, the contact form has no
                        dropdown and every inquiry goes to the fallback
                        addresses below.
                    </div>
                ) : (
                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Topic</TableHead>
                                    <TableHead>Sent to</TableHead>
                                    <TableHead>Form</TableHead>
                                    <TableHead className="text-right">
                                        Received
                                    </TableHead>
                                    <TableHead className="text-right">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {types.map((type) => (
                                    <TableRow key={type.id}>
                                        <TableCell className="font-medium">
                                            {type.name}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {type.email}
                                        </TableCell>
                                        <TableCell>
                                            {type.is_active ? (
                                                <Badge className="border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                    Shown
                                                </Badge>
                                            ) : (
                                                <Badge variant="outline">
                                                    Hidden
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {type.inquiries_count}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-1">
                                                <TypeDialog type={type} />
                                                <DeleteDialog type={type} />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Fallback addresses
                        </CardTitle>
                        <CardDescription>
                            Where an inquiry goes when its topic has no address
                            of its own. Email delivery itself is set up in the
                            server settings.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <FallbackForm emails={fallbackEmails} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

InquiryTypesIndex.layout = {
    breadcrumbs: [
        { title: 'Inquiry topics', href: InquiryTypeController.index() },
    ],
};
