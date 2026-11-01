export type EventState =
    | 'draft'
    | 'scheduled'
    | 'published'
    | 'cancelled'
    | 'past'
    | 'trashed';

export type EventFilter = 'all' | 'upcoming' | 'past' | 'draft' | 'trashed';

export type EventCounts = Record<EventFilter, number>;

/** One line of the events list. */
export type EventRow = {
    id: number;
    title: string;
    slug: string;
    state: EventState;
    starts_at: string;
    timezone: string;
    is_all_day: boolean;
    place: string | null;
    is_featured: boolean;
    show_in_donation_form: boolean;
    image_url: string | null;
};

/** An event as the edit form needs it. Times are wall-clock in the event's own time zone. */
export type EventFormData = {
    id: number;
    title: string;
    slug: string;
    summary: string;
    description: string;
    image: {
        url: string;
        alt: string | null;
        width: number | null;
        height: number | null;
    } | null;
    timezone: string;
    is_all_day: boolean;
    starts_at_local: string | null;
    ends_at_local: string | null;
    location_name: string | null;
    address_line1: string | null;
    address_line2: string | null;
    city: string | null;
    province: string | null;
    postal_code: string | null;
    online_url: string | null;
    registration_url: string | null;
    cost_note: string | null;
    status: 'draft' | 'published' | 'cancelled';
    state: EventState;
    published_at: string | null;
    is_featured: boolean;
    show_in_donation_form: boolean;
    meta_title: string | null;
    meta_description: string | null;
    updated_at: string | null;
    public_url: string | null;
};
