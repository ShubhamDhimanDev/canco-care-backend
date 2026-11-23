import { Head, Link } from '@inertiajs/react';
import { ExternalLink, FileText } from 'lucide-react';
import PageController from '@/actions/App/Http/Controllers/Admin/PageController';
import type { PageSummary } from '@/types';

type Props = {
    pages: PageSummary[];
};

const formatDate = (iso: string | null): string =>
    iso === null
        ? 'Original text'
        : `Edited ${new Date(iso).toLocaleDateString(undefined, { dateStyle: 'medium' })}`;

export default function PagesIndex({ pages }: Props) {
    return (
        <>
            <Head title="Pages" />

            <div className="mx-auto w-full max-w-4xl min-w-0 space-y-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Pages</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        The words and pictures of the website’s other pages. The
                        home page has its own editor; the big impact number is
                        under Site settings.
                    </p>
                </div>

                <ul className="divide-y rounded-xl border bg-card">
                    {pages.map((page) => (
                        <li
                            key={page.slug}
                            className="flex flex-wrap items-center gap-3 p-4"
                        >
                            <FileText
                                aria-hidden="true"
                                className="size-5 shrink-0 text-muted-foreground"
                            />
                            <div className="min-w-0 flex-1">
                                <Link
                                    href={PageController.edit(page.slug)}
                                    className="font-medium hover:underline"
                                >
                                    {page.label}
                                </Link>
                                <p className="text-sm text-muted-foreground">
                                    {page.description}
                                </p>
                            </div>
                            <span className="text-sm text-muted-foreground">
                                {formatDate(page.updated_at)}
                            </span>
                            {page.public_url && (
                                <a
                                    href={page.public_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                                >
                                    View
                                    <ExternalLink
                                        aria-hidden="true"
                                        className="size-3.5"
                                    />
                                </a>
                            )}
                            <Link
                                href={PageController.edit(page.slug)}
                                className="rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-accent"
                            >
                                Edit
                            </Link>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}
