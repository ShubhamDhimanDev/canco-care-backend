import { Form, Head } from '@inertiajs/react';
import EventController from '@/actions/App/Http/Controllers/Admin/EventController';
import EventForm from '@/components/events/event-form';
import EventStateBadge from '@/components/events/event-state-badge';
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
import { formatDateTime } from '@/lib/date';
import type { EventFormData } from '@/types';

export default function EditEvent({
    event,
    timezones,
}: {
    event: EventFormData;
    timezones: string[];
}) {
    return (
        <>
            <Head title={`Edit: ${event.title}`} />

            <div className="mx-auto w-full max-w-6xl space-y-8 p-4">
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-3">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Edit event
                        </h2>
                        <EventStateBadge state={event.state} />
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Last saved {formatDateTime(event.updated_at)}
                    </p>
                </div>

                <EventForm
                    form={EventController.update.form(event.id)}
                    event={event}
                    submitLabel="Save changes"
                    timezones={timezones}
                />

                <div className="rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                    <p className="font-medium text-red-700 dark:text-red-100">
                        Move to trash
                    </p>
                    <p className="mb-3 text-sm text-red-600 dark:text-red-100/80">
                        The event disappears from the website and from the
                        donation form. You can restore it from the trash.
                    </p>

                    <Dialog>
                        <DialogTrigger asChild>
                            <Button
                                variant="destructive"
                                data-test="trash-event-button"
                            >
                                Move to trash
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>
                                Move “{event.title}” to the trash?
                            </DialogTitle>
                            <DialogDescription>
                                It will no longer appear on the website. You can
                                restore it later from the Trash filter.
                            </DialogDescription>

                            <Form
                                {...EventController.destroy.form(event.id)}
                                className="space-y-6"
                            >
                                {({ processing }) => (
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button variant="secondary">
                                                Cancel
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            variant="destructive"
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Move to trash
                                        </Button>
                                    </DialogFooter>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </>
    );
}

EditEvent.layout = {
    breadcrumbs: [
        { title: 'Events', href: EventController.index() },
        { title: 'Edit event', href: EventController.index() },
    ],
};
