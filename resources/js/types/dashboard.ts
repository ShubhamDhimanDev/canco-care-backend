import type { DonationStatus } from './site';
import type { EventState } from './events';
import type { InquiryStatus } from './inquiries';
import type { StoryState } from './stories';

/** What the dashboard receives. `donations` and `system` are null for editors (administrators only). */
export type DashboardData = {
    stories: {
        published: number;
        scheduled: number;
        drafts: number;
        featured: number;
        recent: {
            id: number;
            title: string;
            state: StoryState;
            updated_at: string | null;
        }[];
    };
    events: {
        upcoming: number;
        drafts: number;
        next: {
            id: number;
            title: string;
            state: EventState;
            starts_at: string;
            timezone: string;
            is_all_day: boolean;
            place: string | null;
        }[];
    };
    inquiries: {
        new: number;
        last_30_days: number;
        recent: {
            id: number;
            name: string;
            topic: string | null;
            status: InquiryStatus;
            excerpt: string;
            created_at: string | null;
        }[];
    };
    library: { images: number };
    site: { impact_text: string; donations_open: boolean };
    donations: {
        this_month_cents: number;
        last_month_cents: number;
        total_cents: number;
        gifts: number;
        average_cents: number;
        test_count: number;
        pending: number;
        failed_week: number;
        chart: { days: number; series: { date: string; cents: number }[] };
        recent: {
            id: number;
            donor: string;
            amount_cents: number;
            status: DonationStatus;
            is_test: boolean;
            event: string | null;
            date: string | null;
        }[];
    } | null;
    system: {
        queue_waiting: number | null;
        queue_failed: number | null;
        gateway_problem: string | null;
    } | null;
};
