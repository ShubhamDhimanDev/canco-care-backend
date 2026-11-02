import type { AttachedImage } from '@/types/media';

export type HomeImageNode = { media_id: number | null; alt: string | null };

/** The home page document as the editor receives it (images resolved for preview). */
export type HomeContentForm = {
    hero: {
        title: string;
        text: string;
        primary_label: string;
        secondary_label: string;
        secondary_href: string;
        image: AttachedImage | null;
    };
    intro: {
        eyebrow: string;
        title: string;
        text: string;
        button_label: string;
        button_href: string;
    };
    cards: {
        image: AttachedImage | null;
        eyebrow: string;
        title: string;
        text: string;
    }[];
    stories: { eyebrow: string; title: string };
};

/** The built-in text, shown as placeholders so an editor sees what an empty field falls back to. */
export type HomeDefaults = Omit<HomeContentForm, 'hero' | 'cards'> & {
    hero: Omit<HomeContentForm['hero'], 'image'>;
    cards: Omit<HomeContentForm['cards'][number], 'image'>[];
};
