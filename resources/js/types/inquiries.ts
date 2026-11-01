export type InquiryStatus = 'new' | 'read' | 'replied' | 'archived' | 'spam';

export type InquiryView =
    | 'inbox'
    | 'new'
    | 'replied'
    | 'archived'
    | 'spam'
    | 'all';

export type InquiryCounts = Record<InquiryView, number>;

/** One line of the inbox. */
export type InquiryRow = {
    id: number;
    reference: string;
    name: string;
    email: string;
    type: string | null;
    status: InquiryStatus;
    snippet: string;
    received_at: string | null;
};

export type InquiryDetail = {
    id: number;
    reference: string;
    name: string;
    first_name: string;
    email: string;
    phone: string;
    phone_raw: string;
    type: string | null;
    routed_to: string | null;
    message: string;
    status: InquiryStatus;
    assigned_to: number | null;
    admin_notes: string;
    received_at: string | null;
    replied_at: string | null;
    source_url: string | null;
};

export type InquiryTypeRow = {
    id: number;
    name: string;
    slug: string;
    email: string;
    is_active: boolean;
    sort_order: number;
    inquiries_count: number;
};
