import { Form } from '@inertiajs/react';
import {
    Ban,
    CalendarClock,
    FileText,
    Globe,
    HeartHandshake,
    MapPin,
    MonitorPlay,
    Search,
    Star,
    Ticket,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import EventController from '@/actions/App/Http/Controllers/Admin/EventController';
import AlertError from '@/components/alert-error';
import CheckField from '@/components/check-field';
import FormActionBar, { BarLink } from '@/components/form-action-bar';
import FormField from '@/components/form-field';
import InputError from '@/components/input-error';
import ImagePicker from '@/components/media/image-picker';
import RichTextEditor from '@/components/rich-text-editor';
import SectionCard from '@/components/section-card';
import SegmentedField from '@/components/segmented-field';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFormDirty } from '@/hooks/use-form-dirty';
import { isoToLocalInput, localInputToIso, viewerTimeZone } from '@/lib/date';
import type { EventFormData } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

type Props = {
    form: RouteFormDefinition<'post'>;
    event?: EventFormData;
    /** "New event" / "Edit event", shown in the sticky action bar. */
    title: string;
    subtitle?: ReactNode;
    badge?: ReactNode;
    submitLabel: string;
    timezones: string[];
};

type Status = 'draft' | 'published' | 'cancelled';
type Where = 'in_person' | 'online' | 'both';

const DEFAULT_ZONE = 'America/Vancouver';

/** "America/St_Johns" → "St Johns (America)". */
function zoneLabel(zone: string): string {
    const [region, ...rest] = zone.split('/');

    return `${rest.join(' / ').replaceAll('_', ' ')} (${region})`;
}

/** "2026-03-14T18:00" or "2026-03-14" as a wall-clock Date (no zone maths: it is only printed). */
function wallClock(value: string): Date | null {
    const match = /^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?/.exec(value);

    if (!match) {
        return null;
    }

    return new Date(
        Number(match[1]),
        Number(match[2]) - 1,
        Number(match[3]),
        Number(match[4] ?? 0),
        Number(match[5] ?? 0),
    );
}

/** A plain-words summary of the dates, and a problem if the end is before the start. */
function describeWhen(
    start: string,
    end: string,
    allDay: boolean,
    zone: string,
): { text: string; problem: string | null } {
    const from = wallClock(start);

    if (!from) {
        return { text: 'Pick a start to see how it will read.', problem: null };
    }

    const day = new Intl.DateTimeFormat(undefined, {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
    const time = new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    });
    const to = wallClock(end);
    const sameDay = to !== null && day.format(from) === day.format(to);
    const place = zoneLabel(zone);

    if (to && to.getTime() < from.getTime()) {
        return {
            text: '',
            problem: 'The end is before the start.',
        };
    }

    if (allDay) {
        return {
            text:
                to && !sameDay
                    ? `${day.format(from)} to ${day.format(to)}, all day`
                    : `${day.format(from)}, all day`,
            problem: null,
        };
    }

    if (!to) {
        return {
            text: `${day.format(from)} at ${time.format(from)} · ${place}`,
            problem: null,
        };
    }

    const hours = (to.getTime() - from.getTime()) / 3_600_000;
    const length =
        hours >= 24
            ? `${Math.round(hours / 24)} days`
            : hours >= 1
              ? `${Math.round(hours * 10) / 10} h`
              : `${Math.round(hours * 60)} min`;

    return {
        text: sameDay
            ? `${day.format(from)}, ${time.format(from)} – ${time.format(to)} (${length}) · ${place}`
            : `${day.format(from)} ${time.format(from)} → ${day.format(to)} ${time.format(to)} · ${place}`,
        problem: null,
    };
}

function initialWhere(event?: EventFormData): Where {
    const online = Boolean(event?.online_url);
    const venue = Boolean(
        event?.location_name || event?.city || event?.address_line1,
    );

    if (online && venue) {
        return 'both';
    }

    return online ? 'online' : 'in_person';
}

/**
 * The event form shared by "New event" and "Edit event". Start and end are typed as the wall-clock
 * time in the event's own time zone; the server converts them to UTC.
 */
export default function EventForm({
    form,
    event,
    title: heading,
    subtitle,
    badge,
    submitLabel,
    timezones,
}: Props) {
    const { dirty, clear, bindings } = useFormDirty();
    const [status, setStatus] = useState<Status>(event?.status ?? 'draft');
    const [allDay, setAllDay] = useState(event?.is_all_day ?? false);
    const [start, setStart] = useState(event?.starts_at_local ?? '');
    const [end, setEnd] = useState(event?.ends_at_local ?? '');
    const [timezone, setTimezone] = useState(event?.timezone ?? DEFAULT_ZONE);
    const [where, setWhere] = useState<Where>(initialWhere(event));
    const [publishedLocal, setPublishedLocal] = useState(
        isoToLocalInput(event?.published_at ?? null),
    );
    const [slug, setSlug] = useState(event?.slug ?? '');
    const slugChanged = event !== undefined && slug !== event.slug;

    // Switching between a date and a date-time keeps what was typed.
    const toggleAllDay = (checked: boolean) => {
        setAllDay(checked);
        setStart((value) =>
            checked
                ? value.slice(0, 10)
                : value
                  ? `${value.slice(0, 10)}T18:00`
                  : '',
        );
        setEnd((value) =>
            checked
                ? value.slice(0, 10)
                : value
                  ? `${value.slice(0, 10)}T21:00`
                  : '',
        );
    };

    const inputType = allDay ? 'date' : 'datetime-local';
    const when = describeWhen(start, end, allDay, timezone);
    const showVenue = where !== 'online';
    const showOnline = where !== 'in_person';

    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            onSuccess={clear}
            className="space-y-6"
            {...bindings}
        >
            {({ errors, processing, recentlySuccessful }) => {
                const messages = Object.values(errors);
                const hasSeo =
                    Boolean(event?.meta_title) ||
                    Boolean(event?.meta_description) ||
                    ['meta_title', 'meta_description'].some(
                        (field) => field in errors,
                    );

                return (
                    <>
                        <FormActionBar
                            title={heading}
                            subtitle={subtitle}
                            badge={badge}
                            back={{
                                href: EventController.index(),
                                label: 'Back to events',
                            }}
                            extra={
                                event?.public_url &&
                                (event.state === 'published' ||
                                    event.state === 'cancelled' ||
                                    event.state === 'past') ? (
                                    <BarLink href={event.public_url}>
                                        View
                                    </BarLink>
                                ) : null
                            }
                            submitLabel={submitLabel}
                            submitTest="save-event-button"
                            processing={processing}
                            dirty={dirty}
                            saved={recentlySuccessful}
                        />

                        {messages.length > 0 && (
                            <div role="alert">
                                <AlertError
                                    errors={messages}
                                    title="The event was not saved"
                                />
                            </div>
                        )}

                        <div className="grid gap-6 lg:grid-cols-3">
                            <div className="space-y-6 lg:col-span-2">
                                <FormField
                                    name="title"
                                    label="Title"
                                    defaultValue={event?.title}
                                    placeholder="What is the event called?"
                                    required
                                    maxLength={200}
                                    counter
                                    error={errors.title}
                                />

                                <FormField
                                    name="summary"
                                    label="Short summary"
                                    defaultValue={event?.summary}
                                    required
                                    rows={3}
                                    maxLength={300}
                                    counter
                                    help="Shown on the event card and in search results."
                                    error={errors.summary}
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor="description">Details</Label>
                                    <RichTextEditor
                                        id="description"
                                        name="description"
                                        label="Event details"
                                        defaultValue={event?.description ?? ''}
                                        invalid={Boolean(errors.description)}
                                    />
                                    <InputError message={errors.description} />
                                </div>

                                <SectionCard
                                    icon={CalendarClock}
                                    title="When"
                                    description="Type the time as it is where the event happens — the website shows it in that time zone."
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor="timezone">
                                            Time zone
                                        </Label>
                                        <Select
                                            name="timezone"
                                            value={timezone}
                                            onValueChange={setTimezone}
                                        >
                                            <SelectTrigger
                                                id="timezone"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {timezones.map((zone) => (
                                                    <SelectItem
                                                        key={zone}
                                                        value={zone}
                                                    >
                                                        {zoneLabel(zone)}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.timezone} />
                                    </div>

                                    <CheckField
                                        name="is_all_day"
                                        label="All-day event"
                                        description="No start or end time, just the date."
                                        defaultChecked={allDay}
                                        onCheckedChange={toggleAllDay}
                                    />

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="starts_at_local">
                                                Starts
                                            </Label>
                                            <Input
                                                id="starts_at_local"
                                                name="starts_at_local"
                                                type={inputType}
                                                value={start}
                                                onChange={(e) =>
                                                    setStart(e.target.value)
                                                }
                                                required
                                                aria-invalid={
                                                    errors.starts_at_local
                                                        ? true
                                                        : undefined
                                                }
                                            />
                                            <InputError
                                                message={errors.starts_at_local}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="ends_at_local">
                                                Ends (optional)
                                            </Label>
                                            <Input
                                                id="ends_at_local"
                                                name="ends_at_local"
                                                type={inputType}
                                                value={end}
                                                min={start || undefined}
                                                onChange={(e) =>
                                                    setEnd(e.target.value)
                                                }
                                                aria-invalid={
                                                    errors.ends_at_local ||
                                                    when.problem
                                                        ? true
                                                        : undefined
                                                }
                                            />
                                            <InputError
                                                message={errors.ends_at_local}
                                            />
                                        </div>
                                    </div>

                                    <p
                                        role="status"
                                        className={
                                            when.problem
                                                ? 'rounded-md bg-destructive-foreground/10 px-3 py-2 text-sm text-destructive-foreground'
                                                : 'rounded-md bg-muted/60 px-3 py-2 text-sm'
                                        }
                                    >
                                        {when.problem ?? when.text}
                                    </p>
                                </SectionCard>

                                <SectionCard
                                    icon={MapPin}
                                    title="Where"
                                    description="A venue, a city or an online link — at least one."
                                >
                                    <SegmentedField
                                        label="How do people attend?"
                                        value={where}
                                        onValueChange={setWhere}
                                        options={[
                                            {
                                                value: 'in_person',
                                                label: 'In person',
                                                icon: Users,
                                            },
                                            {
                                                value: 'online',
                                                label: 'Online',
                                                icon: MonitorPlay,
                                            },
                                            {
                                                value: 'both',
                                                label: 'Both',
                                                icon: Globe,
                                            },
                                        ]}
                                    />

                                    {/* A disabled fieldset is not submitted, so a hidden part is cleared on save. */}
                                    <fieldset
                                        disabled={!showVenue}
                                        hidden={!showVenue}
                                        className="space-y-4"
                                    >
                                        <FormField
                                            name="location_name"
                                            label="Venue name"
                                            defaultValue={event?.location_name}
                                            maxLength={160}
                                            error={errors.location_name}
                                        />
                                        <FormField
                                            name="address_line1"
                                            label="Street address"
                                            defaultValue={event?.address_line1}
                                            maxLength={255}
                                            autoComplete="off"
                                            error={errors.address_line1}
                                        />
                                        <FormField
                                            name="address_line2"
                                            label="Suite, floor (optional)"
                                            defaultValue={event?.address_line2}
                                            maxLength={255}
                                            autoComplete="off"
                                            error={errors.address_line2}
                                        />
                                        <div className="grid gap-4 sm:grid-cols-3">
                                            <FormField
                                                name="city"
                                                label="City"
                                                defaultValue={event?.city}
                                                maxLength={100}
                                                error={errors.city}
                                            />
                                            <FormField
                                                name="province"
                                                label="Province"
                                                defaultValue={event?.province}
                                                maxLength={2}
                                                placeholder="BC"
                                                help="Two letters."
                                                error={errors.province}
                                            />
                                            <FormField
                                                name="postal_code"
                                                label="Postal code"
                                                defaultValue={
                                                    event?.postal_code
                                                }
                                                maxLength={10}
                                                placeholder="V1X 2A1"
                                                error={errors.postal_code}
                                            />
                                        </div>
                                    </fieldset>

                                    <fieldset
                                        disabled={!showOnline}
                                        hidden={!showOnline}
                                    >
                                        <FormField
                                            name="online_url"
                                            label="Online link"
                                            type="url"
                                            defaultValue={event?.online_url}
                                            placeholder="https://"
                                            maxLength={255}
                                            help="Where people join the virtual event."
                                            error={errors.online_url}
                                        />
                                    </fieldset>
                                </SectionCard>

                                <SectionCard
                                    icon={Ticket}
                                    title="Joining"
                                    description="Optional. Where to register, and what it costs."
                                    contentClassName="grid gap-4 space-y-0 sm:grid-cols-2"
                                >
                                    <FormField
                                        name="registration_url"
                                        label="Registration or tickets link"
                                        type="url"
                                        defaultValue={event?.registration_url}
                                        placeholder="https://"
                                        maxLength={255}
                                        error={errors.registration_url}
                                    />
                                    <FormField
                                        name="cost_note"
                                        label="Cost"
                                        defaultValue={event?.cost_note}
                                        placeholder="Free, or $25 per ticket"
                                        maxLength={120}
                                        error={errors.cost_note}
                                    />
                                </SectionCard>

                                <SectionCard
                                    collapsible
                                    defaultOpen={hasSeo}
                                    icon={Search}
                                    title="Search results"
                                    description="Optional. How the event appears in search."
                                >
                                    <FormField
                                        name="meta_title"
                                        label="Search title"
                                        defaultValue={event?.meta_title}
                                        maxLength={255}
                                        counter
                                        error={errors.meta_title}
                                    />
                                    <FormField
                                        name="meta_description"
                                        label="Search description"
                                        defaultValue={event?.meta_description}
                                        rows={2}
                                        maxLength={255}
                                        counter
                                        error={errors.meta_description}
                                    />
                                </SectionCard>
                            </div>

                            <aside className="space-y-6">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            Publishing
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-5">
                                        <div className="grid gap-2">
                                            <SegmentedField
                                                name="status"
                                                label="Status"
                                                value={status}
                                                onValueChange={setStatus}
                                                options={[
                                                    {
                                                        value: 'draft',
                                                        label: 'Draft',
                                                        icon: FileText,
                                                    },
                                                    {
                                                        value: 'published',
                                                        label: 'Live',
                                                        icon: Globe,
                                                    },
                                                    {
                                                        value: 'cancelled',
                                                        label: 'Cancelled',
                                                        icon: Ban,
                                                    },
                                                ]}
                                            />
                                            <p
                                                role="status"
                                                className="rounded-md bg-muted/60 px-3 py-2 text-xs text-muted-foreground"
                                            >
                                                {status === 'draft' &&
                                                    'Not on the website. Only staff can see it.'}
                                                {status === 'published' &&
                                                    'Listed on the Upcoming Events page.'}
                                                {status === 'cancelled' &&
                                                    'Stays on the website, clearly marked as cancelled, so shared links still work.'}
                                            </p>
                                            <InputError
                                                message={errors.status}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="published_at_local">
                                                Show on the website from
                                            </Label>
                                            <Input
                                                id="published_at_local"
                                                type="datetime-local"
                                                value={publishedLocal}
                                                onChange={(e) =>
                                                    setPublishedLocal(
                                                        e.target.value,
                                                    )
                                                }
                                                aria-describedby="published-help"
                                            />
                                            <input
                                                type="hidden"
                                                name="published_at"
                                                value={localInputToIso(
                                                    publishedLocal,
                                                )}
                                            />
                                            <p
                                                id="published-help"
                                                className="text-xs text-muted-foreground"
                                            >
                                                Leave empty to show it as soon
                                                as you save. A future date
                                                schedules it. Times are in your
                                                time zone ({viewerTimeZone()}).
                                            </p>
                                            <InputError
                                                message={errors.published_at}
                                            />
                                        </div>

                                        <FormField
                                            name="slug"
                                            label="Web address"
                                            prefix="/events/"
                                            defaultValue={event?.slug}
                                            maxLength={220}
                                            placeholder="built-from-the-title"
                                            onValueChange={setSlug}
                                            help={
                                                slugChanged
                                                    ? 'The old address will keep working and send visitors here.'
                                                    : 'Lowercase letters, numbers and hyphens. Leave empty to build it from the title.'
                                            }
                                            error={errors.slug}
                                        />

                                        <CheckField
                                            name="is_featured"
                                            icon={Star}
                                            label="Feature this event"
                                            description="Featured events are listed first on the Upcoming Events page."
                                            defaultChecked={
                                                event?.is_featured ?? false
                                            }
                                        />
                                        <CheckField
                                            name="show_in_donation_form"
                                            icon={HeartHandshake}
                                            label="Show in the donation form"
                                            description="Donors can choose this event in the “Support an event” list when they donate."
                                            defaultChecked={
                                                event?.show_in_donation_form ??
                                                false
                                            }
                                        />
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            Picture
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <ImagePicker
                                            fields={{
                                                mediaId: 'image_media_id',
                                                alt: 'image_alt',
                                                remove: 'remove_image',
                                            }}
                                            current={event?.image ?? null}
                                            altRequired
                                            minWidth={800}
                                            error={errors.image_media_id}
                                            altError={errors.image_alt}
                                        />
                                    </CardContent>
                                </Card>
                            </aside>
                        </div>
                    </>
                );
            }}
        </Form>
    );
}
