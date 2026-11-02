/** One file in the media library, as the media screens and the picker receive it. */
export type MediaItem = {
    id: number;
    name: string;
    url: string;
    thumb_url: string;
    path: string;
    width: number;
    height: number;
    size_bytes: number;
    alt: string | null;
    is_webp: boolean;
    created_at: string | null;
};

/** An image already attached to a record (story, event, home page): what the picker field shows. */
export type AttachedImage = {
    url: string;
    alt: string | null;
    width: number | null;
    height: number | null;
};
