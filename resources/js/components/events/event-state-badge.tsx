import { Badge } from '@/components/ui/badge';
import type { EventState } from '@/types';

const STATES: Record<EventState, { label: string; className: string }> = {
    published: {
        label: 'Published',
        className:
            'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    },
    scheduled: {
        label: 'Scheduled',
        className:
            'border-transparent bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    },
    draft: {
        label: 'Draft',
        className: 'border-transparent bg-secondary text-secondary-foreground',
    },
    cancelled: {
        label: 'Cancelled',
        className:
            'border-transparent bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-300',
    },
    past: {
        label: 'Past',
        className: 'border-transparent bg-muted text-muted-foreground',
    },
    trashed: {
        label: 'In trash',
        className: 'border-destructive/40 text-destructive',
    },
};

export default function EventStateBadge({ state }: { state: EventState }) {
    const { label, className } = STATES[state];

    return <Badge className={className}>{label}</Badge>;
}
