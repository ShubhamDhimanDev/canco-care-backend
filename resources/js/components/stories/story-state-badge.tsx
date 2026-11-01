import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { StoryState } from '@/types';

const STATES: Record<StoryState, { label: string; className: string }> = {
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
    trashed: {
        label: 'In trash',
        className: 'border-destructive/40 text-destructive',
    },
};

export default function StoryStateBadge({ state }: { state: StoryState }) {
    const { label, className } = STATES[state];

    return <Badge className={cn(className)}>{label}</Badge>;
}
