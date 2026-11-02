import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /** Highlight this item for every page under its address (e.g. /stories/12/edit), not only the exact one. */
    matchPrefix?: boolean;
    /** A count shown next to the title (for example unread inquiries); hidden when 0. */
    badge?: number;
};
