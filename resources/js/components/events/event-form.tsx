import { Form } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import AlertError from '@/components/alert-error';
import CheckField from '@/components/check-field';
import FormField from '@/components/form-field';
import InputError from '@/components/input-error';
import ImagePicker from '@/components/media/image-picker';
import RichTextEditor from '@/components/rich-text-editor';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { isoToLocalInput, localInputToIso, viewerTimeZone } from '@/lib/date';
import type { EventFormData } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

type Props = {
    form: RouteFormDefinition<'post'>;
    event?: EventFormData;
    submitLabel: string;
    timezones: string[];
};

const DEFAULT_ZONE = 'America/Vancouver';

/** "America/St_Johns" → "St Johns (America)". */
function zoneLabel(zone: string): string {
    const [region, ...rest] = zone.split('/');

    return `${rest.join(' / ').replaceAll('_', ' ')} (${region})`;
}

/**
 * The event form shared by "New event" and "Edit event". Start and end are typed as the wall-clock
 * time in the event's own time zone; the server converts them to UTC.
 */
export default function EventForm({
    form,
    event,
    submitLabel,
    timezones,
}: Props) {
    const [allDay, setAllDay] = useState(event?.is_all_day ?? false);
    const [start, setStart] = useState(event?.starts_at_local ?? '');
    const [end, setEnd] = useState(event?.ends_at_local ?? '');
    const [timezone, setTimezone] = useState(event?.timezone ?? DEFAULT_ZONE);
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

    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            className="grid gap-6 lg:grid-cols-3"
        >
            {({ errors, processing }) => {
                const messages = Object.values(errors);
                const hasSeo =
                    Boolean(event?.meta_title) ||
                    Boolean(event?.meta_description) ||
                    ['meta_title', 'meta_description'].some(
                        (field) => field in errors,
                    );

                return (
                    <>
                        <div className="space-y-6 lg:col-span-2">
                            {messages.length > 0 && (
                                <div role="alert">
                                    <AlertError
                                        errors={messages}
                                        title="The event was not saved"
                                    />
                                </div>
                            )}

                            <FormField
                                name="title"
                                label="Title"
                                defaultValue={event?.title}
                                required
                                maxLength={200}
                                error={errors.title}
                            />

                            <FormField
                                name="summary"
                                label="Short summary"
                                defaultValue={event?.summary}
                                required
                                rows={3}
                                maxLength={300}
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

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        When
                                    </CardTitle>
                                    <CardDescription>
                                        Type the time as it is where the event
                                        happens — the website shows it in that
                                        time zone.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
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
                                                    errors.ends_at_local
                                                        ? true
                                                        : undefined
                                                }
                                            />
                                            <InputError
                                                message={errors.ends_at_local}
                                            />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Where
                                    </CardTitle>
                                    <CardDescription>
                                        A venue, a city or an online link — at
                                        least one.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
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
                                            defaultValue={event?.postal_code}
                                            maxLength={10}
                                            placeholder="V1X 2A1"
                                            error={errors.postal_code}
                                        />
                                    </div>
                                    <FormField
                                        name="online_url"
                                        label="Online link (for a virtual event)"
                                        type="url"
                                        defaultValue={event?.online_url}
                                        placeholder="https://"
                                        maxLength={255}
                                        error={errors.online_url}
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Joining
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
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
                                </CardContent>
                            </Card>

                            <Collapsible defaultOpen={hasSeo}>
                                <Card>
                                    <CardHeader>
                                        <CollapsibleTrigger asChild>
                                            <button
                                                type="button"
                                                className="group flex w-full items-center justify-between text-left"
                                            >
                                                <span>
                                                    <CardTitle className="text-base">
                                                        Search results
                                                    </CardTitle>
                                                    <CardDescription>
                                                        Optional. How the event
                                                        appears in search.
                                                    </CardDescription>
                                                </span>
                                                <ChevronDown className="size-4 transition-transform group-data-[state=open]:rotate-180" />
                                            </button>
                                        </CollapsibleTrigger>
                                    </CardHeader>
                                    <CollapsibleContent>
                                        <CardContent className="space-y-4">
                                            <FormField
                                                name="meta_title"
                                                label="Search title"
                                                defaultValue={event?.meta_title}
                                                maxLength={255}
                                                error={errors.meta_title}
                                            />
                                            <FormField
                                                name="meta_description"
                                                label="Search description"
                                                defaultValue={
                                                    event?.meta_description
                                                }
                                                rows={2}
                                                maxLength={255}
                                                error={errors.meta_description}
                                            />
                                        </CardContent>
                                    </CollapsibleContent>
                                </Card>
                            </Collapsible>
                        </div>

                        <aside className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Publishing
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="status">Status</Label>
                                        <Select
                                            name="status"
                                            defaultValue={
                                                event?.status ?? 'draft'
                                            }
                                        >
                                            <SelectTrigger
                                                id="status"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="draft">
                                                    Draft — not on the website
                                                </SelectItem>
                                                <SelectItem value="published">
                                                    Published
                                                </SelectItem>
                                                <SelectItem value="cancelled">
                                                    Cancelled — shown as
                                                    cancelled
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.status} />
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
                                            Leave empty to show it as soon as
                                            you save. A future date schedules
                                            it. Times are in your time zone (
                                            {viewerTimeZone()}).
                                        </p>
                                        <InputError
                                            message={errors.published_at}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="slug">
                                            Web address
                                        </Label>
                                        <Input
                                            id="slug"
                                            name="slug"
                                            value={slug}
                                            onChange={(e) =>
                                                setSlug(e.target.value)
                                            }
                                            maxLength={220}
                                            placeholder="Built from the title"
                                            aria-describedby="slug-help"
                                            aria-invalid={
                                                errors.slug ? true : undefined
                                            }
                                        />
                                        <p
                                            id="slug-help"
                                            className="text-xs text-muted-foreground"
                                        >
                                            {slugChanged
                                                ? 'The old address will keep working and send visitors here.'
                                                : 'Lowercase letters, numbers and hyphens. Leave empty to build it from the title.'}
                                        </p>
                                        <InputError message={errors.slug} />
                                    </div>

                                    <CheckField
                                        name="is_featured"
                                        label="Feature this event"
                                        description="Featured events are listed first on the Upcoming Events page."
                                        defaultChecked={
                                            event?.is_featured ?? false
                                        }
                                    />
                                    <CheckField
                                        name="show_in_donation_form"
                                        label="Show in the donation form"
                                        description="Donors can choose this event in the “Support an event” list when they donate."
                                        defaultChecked={
                                            event?.show_in_donation_form ??
                                            false
                                        }
                                    />

                                    <div className="flex flex-wrap items-center gap-3 pt-2">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            data-test="save-event-button"
                                        >
                                            {processing && <Spinner />}
                                            {submitLabel}
                                        </Button>
                                        {event?.public_url &&
                                            (event.state === 'published' ||
                                                event.state === 'cancelled' ||
                                                event.state === 'past') && (
                                                <a
                                                    href={event.public_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-sm underline underline-offset-4"
                                                >
                                                    View on website
                                                </a>
                                            )}
                                    </div>
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
                    </>
                );
            }}
        </Form>
    );
}
