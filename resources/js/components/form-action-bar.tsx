import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { ArrowLeft, Check, ExternalLink } from 'lucide-react';
import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    /** Small line under the title: "Last saved …", a hint. */
    subtitle?: ReactNode;
    /** A state badge next to the title. */
    badge?: ReactNode;
    back?: { href: NonNullable<InertiaLinkProps['href']>; label: string };
    /** "View on website" and similar; rendered before the save button. */
    extra?: ReactNode;
    submitLabel: string;
    processing: boolean;
    dirty?: boolean;
    /** True for a few seconds after a successful save. */
    saved?: boolean;
    /** Defaults to the page title; used for the save button's `data-test`. */
    submitTest?: string;
    className?: string;
};

/**
 * The bar with a form's actions. It sticks to the top of the screen while the form scrolls, so the
 * editor never has to scroll back to save. Render it inside the <form>; Ctrl/⌘+S submits.
 */
export default function FormActionBar({
    title,
    subtitle,
    badge,
    back,
    extra,
    submitLabel,
    processing,
    dirty = false,
    saved = false,
    submitTest,
    className,
}: Props) {
    const bar = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (
                (event.ctrlKey || event.metaKey) &&
                event.key.toLowerCase() === 's'
            ) {
                event.preventDefault();
                bar.current?.closest('form')?.requestSubmit();
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, []);

    return (
        <div
            ref={bar}
            className={cn(
                'sticky top-2 z-30 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-xl border bg-background/90 px-4 py-2.5 shadow-sm backdrop-blur before:pointer-events-none before:absolute before:inset-x-0 before:-top-2 before:h-2 before:bg-background supports-[backdrop-filter]:bg-background/75',
                className,
            )}
        >
            <div className="flex min-w-0 items-center gap-3">
                {back && (
                    <Button
                        asChild
                        variant="ghost"
                        size="icon"
                        className="-ml-1 shrink-0"
                    >
                        <Link
                            href={back.href}
                            aria-label={back.label}
                            title={back.label}
                        >
                            <ArrowLeft />
                        </Link>
                    </Button>
                )}
                <div className="min-w-0">
                    <div className="flex items-center gap-2">
                        <h2 className="truncate text-base leading-tight font-semibold">
                            {title}
                        </h2>
                        {badge}
                    </div>
                    {subtitle && (
                        <p className="truncate text-xs text-muted-foreground">
                            {subtitle}
                        </p>
                    )}
                </div>
            </div>

            <div className="flex items-center gap-3">
                <span
                    role="status"
                    aria-live="polite"
                    className="hidden items-center gap-1.5 text-xs text-muted-foreground sm:flex"
                >
                    {processing ? (
                        'Saving…'
                    ) : saved && !dirty ? (
                        <>
                            <Check className="size-3.5 text-emerald-600 dark:text-emerald-400" />
                            Saved
                        </>
                    ) : dirty ? (
                        <>
                            <span
                                className="size-2 rounded-full bg-amber-500"
                                aria-hidden="true"
                            />
                            Unsaved changes
                        </>
                    ) : null}
                </span>
                {extra}
                <Button
                    type="submit"
                    disabled={processing}
                    title="Save (Ctrl+S)"
                    data-test={submitTest}
                >
                    {processing && <Spinner />}
                    {submitLabel}
                </Button>
            </div>
        </div>
    );
}

/** A "View on website" style link for the bar's `extra` slot. */
export function BarLink({
    href,
    children,
}: {
    href: string;
    children: ReactNode;
}) {
    return (
        <Button asChild variant="outline" size="sm">
            <a href={href} target="_blank" rel="noreferrer">
                {children}
                <ExternalLink />
            </a>
        </Button>
    );
}
