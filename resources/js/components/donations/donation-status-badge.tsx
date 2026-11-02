import { Badge } from '@/components/ui/badge';
import type { DonationStatus } from '@/types';

const STATUSES: Record<DonationStatus, { label: string; className: string }> = {
    succeeded: {
        label: 'Succeeded',
        className:
            'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    },
    pending: {
        label: 'Pending',
        className:
            'border-transparent bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    },
    failed: {
        label: 'Failed',
        className:
            'border-destructive-foreground/40 text-destructive-foreground',
    },
    canceled: {
        label: 'Canceled',
        className: 'border-transparent bg-muted text-muted-foreground',
    },
    refunded: {
        label: 'Refunded',
        className:
            'border-transparent bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-300',
    },
    partially_refunded: {
        label: 'Partly refunded',
        className:
            'border-transparent bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-300',
    },
};

export default function DonationStatusBadge({
    status,
}: {
    status: DonationStatus;
}) {
    const { label, className } = STATUSES[status];

    return <Badge className={className}>{label}</Badge>;
}
