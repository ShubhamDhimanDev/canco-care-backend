import { usePage } from '@inertiajs/react';

/**
 * What the signed-in person may do, for hiding buttons and menu entries. The server checks every
 * request again, so this is only about not offering things that would be refused.
 */
export function usePermissions() {
    const { permissions, isAdmin } = usePage().props;

    return {
        isAdmin,
        can: (permission: string) => permissions.includes(permission),
        canAny: (...names: string[]) =>
            names.some((name) => permissions.includes(name)),
    };
}
