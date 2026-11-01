export type StoryState = 'draft' | 'scheduled' | 'published' | 'trashed';

export type StoryImage = {
    url: string;
    alt: string | null;
    width: number | null;
    height: number | null;
};

/** One line of the stories list. */
export type StoryRow = {
    id: number;
    title: string;
    slug: string;
    state: StoryState;
    published_at: string | null;
    updated_at: string | null;
    image_url: string | null;
};

/** A story as the edit form needs it. */
export type StoryFormData = {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    body: string;
    image: StoryImage | null;
    status: 'draft' | 'published';
    state: StoryState;
    published_at: string | null;
    meta_title: string | null;
    meta_description: string | null;
    author_name: string | null;
    updated_at: string | null;
    public_url: string | null;
};

export type StoryFilter =
    | 'all'
    | 'published'
    | 'scheduled'
    | 'draft'
    | 'trashed';

export type StoryCounts = Record<StoryFilter, number>;

/** Laravel's paginator as Inertia serialises it. */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};
