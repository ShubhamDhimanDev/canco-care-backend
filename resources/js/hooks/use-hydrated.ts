import { useSyncExternalStore } from 'react';

const subscribe = () => () => {};

/**
 * False while the server renders and during hydration, true afterwards. Use it to hold back text that
 * depends on the viewer's clock, locale or time zone, so the server and browser HTML always match.
 */
export function useHydrated(): boolean {
    return useSyncExternalStore(
        subscribe,
        () => true,
        () => false,
    );
}
