import { usePage } from '@inertiajs/react';
import AccountLayout from '@/layouts/account-layout';
import AppLayout from '@/layouts/app-layout';
import type { AppLayoutProps } from '@/types';

/** Shared pages (profile, password, two-factor): staff see the back office; everyone else their account menu. */
export default function ShellLayout(props: AppLayoutProps) {
    const user = (
        usePage().props.auth as { user?: { type?: string } } | undefined
    )?.user;

    return user?.type === 'staff' ? (
        <AppLayout {...props} />
    ) : (
        <AccountLayout {...props} />
    );
}
