import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import { cn } from '@/lib/utils';

export const TITLE_LIMIT = 60;
export const DESCRIPTION_LIMIT = 160;

type Props = {
    /** Public origin of the website, without a trailing slash (for the preview address). */
    siteUrl: string;
    title: string;
    slug: string;
    metaTitle: string;
    metaDescription: string;
    /** Used when no search description is typed (the quick answer, the summary or the start of the text). */
    fallbackDescription: string;
    hasImage: boolean;
    tagCount: number;
    faqCount: number;
    hasQuickAnswer: boolean;
};

/** "12 / 60", turning amber once over the limit. */
export function Counter({ length, limit }: { length: number; limit: number }) {
    return (
        <span
            className={cn(
                'text-xs tabular-nums',
                length > limit
                    ? 'text-amber-700 dark:text-amber-400'
                    : 'text-muted-foreground',
            )}
        >
            {length} / {limit}
        </span>
    );
}

function clip(text: string, limit: number): string {
    return text.length > limit
        ? `${text.slice(0, limit - 1).trimEnd()}…`
        : text;
}

/**
 * How the story will look in search results, plus a short checklist of what search engines and AI
 * answer engines reward. Advice only: nothing here blocks saving.
 */
export default function SeoPanel({
    siteUrl,
    title,
    slug,
    metaTitle,
    metaDescription,
    fallbackDescription,
    hasImage,
    tagCount,
    faqCount,
    hasQuickAnswer,
}: Props) {
    const shownTitle = metaTitle.trim() || title.trim() || 'Story title';
    const shownDescription =
        metaDescription.trim() || fallbackDescription.trim();
    const host = siteUrl.replace(/^https?:\/\//, '') || 'cancocares.ca';

    const checks: { ok: boolean; text: string }[] = [
        {
            ok: shownTitle.length >= 20 && shownTitle.length <= TITLE_LIMIT,
            text: `Search title is 20–${TITLE_LIMIT} characters (now ${shownTitle.length})`,
        },
        {
            ok:
                shownDescription.length >= 70 &&
                shownDescription.length <= DESCRIPTION_LIMIT,
            text: `Search description is 70–${DESCRIPTION_LIMIT} characters (now ${shownDescription.length})`,
        },
        { ok: hasImage, text: 'Has a featured image with a description' },
        { ok: tagCount > 0, text: 'Has at least one tag' },
        {
            ok: hasQuickAnswer,
            text: 'Has a quick answer (one or two sentences that answer “what is this about?”)',
        },
        {
            ok: faqCount > 0,
            text: 'Has at least one question and answer (FAQ)',
        },
        {
            ok: slug.length > 0 && slug.length <= 70,
            text: 'The web address is short (70 characters or fewer)',
        },
    ];

    return (
        <div className="space-y-4">
            <div
                className="rounded-md border bg-muted/40 p-3"
                aria-label="Search result preview"
            >
                <p className="m-0 truncate text-xs text-muted-foreground">
                    {host} › stories › {slug || '…'}
                </p>
                <p className="m-0 mt-0.5 text-lg leading-snug text-sky-700 dark:text-sky-400">
                    {clip(shownTitle, TITLE_LIMIT)}
                </p>
                <p className="m-0 mt-0.5 text-sm text-muted-foreground">
                    {shownDescription
                        ? clip(shownDescription, DESCRIPTION_LIMIT)
                        : 'No description yet — search engines will pick text from the story.'}
                </p>
            </div>

            <ul className="m-0 grid list-none gap-1.5 p-0 text-sm">
                {checks.map((check) => (
                    <li key={check.text} className="flex items-start gap-2">
                        {check.ok ? (
                            <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                        ) : (
                            <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600" />
                        )}
                        <span
                            className={cn(!check.ok && 'text-muted-foreground')}
                        >
                            {check.text}
                            <span className="sr-only">
                                {check.ok ? ' — done' : ' — could be improved'}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
