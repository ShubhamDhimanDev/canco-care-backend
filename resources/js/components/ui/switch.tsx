import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * An on/off switch built on a native checkbox (`role="switch"`), so it posts with an ordinary form
 * (`name=value` when on, nothing when off), is keyboard-operable and needs no extra dependency.
 */
function Switch({
    className,
    ...props
}: Omit<React.ComponentProps<'input'>, 'type' | 'role'>) {
    return (
        <span
            data-slot="switch"
            className={cn('relative inline-flex h-6 w-11 shrink-0', className)}
        >
            <input
                type="checkbox"
                role="switch"
                className="peer absolute inset-0 z-10 m-0 size-full cursor-pointer appearance-none opacity-0 disabled:cursor-not-allowed"
                {...props}
            />
            <span
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 rounded-full bg-neutral-300 ring-1 ring-black/5 ring-inset transition-colors peer-checked:bg-primary peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50 peer-disabled:opacity-50 dark:bg-neutral-600 dark:ring-white/10 dark:peer-checked:bg-primary"
            />
            <span
                aria-hidden="true"
                className="pointer-events-none absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow-sm transition-all peer-checked:translate-x-5 peer-checked:bg-primary-foreground dark:bg-neutral-200 dark:peer-checked:bg-primary-foreground"
            />
        </span>
    );
}

export { Switch };
