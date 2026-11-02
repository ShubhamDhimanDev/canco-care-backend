export type SiteSettingsValues = {
    impact: {
        mode: 'custom' | 'actual';
        amount_text: string;
        caption: string;
        blurb: string;
        starting_amount: string;
    };
    social: { facebook: string; instagram: string; linkedin: string };
    contact: { inquiry_email: string };
    donations: { enabled: boolean; presets: number[]; min: string };
    receipts: {
        charity_name: string;
        address: string;
        registration_number: string;
        signatory_name: string;
        signatory_label: string;
        reply_to: string;
    };
};

export type DonationStatus =
    | 'pending'
    | 'succeeded'
    | 'failed'
    | 'canceled'
    | 'refunded'
    | 'partially_refunded';

export type DonationView =
    | 'all'
    | 'succeeded'
    | 'pending'
    | 'failed'
    | 'refunded';

export type DonationRow = {
    id: number;
    short_reference: string;
    status: DonationStatus;
    is_test: boolean;
    amount_cents: number;
    donor: string;
    event: string | null;
    receipt_number: string | null;
    date: string | null;
};

export type DonationDetail = DonationRow & {
    reference: string;
    email: string;
    phone: string | null;
    address: string[];
    gateway: string;
    transaction_id: string | null;
    response: string;
    failure_reason: string | null;
    card: string | null;
    refunded_cents: number;
    receipt_issued_at: string | null;
    receipt_sent_at: string | null;
    consent_at: string;
    can_resend: boolean;
    trail: {
        id: number;
        type: string;
        payload: Record<string, unknown> | null;
        at: string | null;
    }[];
};
