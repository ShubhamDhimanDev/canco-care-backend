import { Head } from '@inertiajs/react';
import EventController from '@/actions/App/Http/Controllers/Admin/EventController';
import EventForm from '@/components/events/event-form';

export default function CreateEvent({ timezones }: { timezones: string[] }) {
    return (
        <>
            <Head title="New event" />

            <div className="mx-auto w-full max-w-6xl p-4">
                <EventForm
                    form={EventController.store.form()}
                    title="New event"
                    subtitle="Save it as a draft first, or publish it right away."
                    submitLabel="Create event"
                    timezones={timezones}
                />
            </div>
        </>
    );
}

CreateEvent.layout = {
    breadcrumbs: [
        { title: 'Events', href: EventController.index() },
        { title: 'New event', href: EventController.create() },
    ],
};
