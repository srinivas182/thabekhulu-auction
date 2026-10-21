import { usePage } from '@inertiajs/react';

/**
 * Checks the signed-in user's permissions for showing or hiding UI.
 * The server always enforces access; this only affects what is displayed.
 */
export function useCan(): (permission?: string) => boolean {
    const { auth } = usePage().props;
    const permissions = auth.permissions ?? [];

    return (permission?: string) =>
        permission === undefined || permissions.includes(permission);
}
