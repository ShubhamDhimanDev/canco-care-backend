import { Badge } from '@/components/ui/badge';
import type { InquiryStatus } from '@/types';

const STATUSES: Record<InquiryStatus, { label: string; className: string }> = {
    new: {
        label: 'New',
        // Orange is reserved for "needs attention" in the admin.
        className:
            'border-transparent bg-orange-100 text-orange-900 dark:bg-orange-950 dark:text-orange-300',
    },
    read: {
        label: 'Read',
        className: 'border-transparent bg-secondary text-secondary-foreground',
    },
    replied: {
        label: 'Replied',
        className:
            'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    },
    archived: {
        label: 'Archived',
        className: 'border-transparent bg-muted text-muted-foreground',
    },
    spam: {
        label: 'Spam',
        className:
            'border-destructive-foreground/40 text-destructive-foreground',
    },
};

export default function InquiryStatusBadge({
    status,
}: {
    status: InquiryStatus;
}) {
    const { label, className } = STATUSES[status];

    return <Badge className={className}>{label}</Badge>;
}
