import type { AttachedImage } from './media';

export type PageField = {
    type: 'text' | 'textarea' | 'rich' | 'image';
    label: string;
    default: string | null;
    max: number | null;
    help: string | null;
};

export type PageSection = {
    title: string;
    description: string | null;
    fields: Record<string, PageField>;
};

/** One row of the pages list. */
export type PageSummary = {
    slug: string;
    label: string;
    description: string;
    path: string;
    public_url: string | null;
    updated_at: string | null;
};

/** Text fields hold text; an image field holds the chosen library picture or null. */
export type PageValues = Record<string, string | AttachedImage | null>;
