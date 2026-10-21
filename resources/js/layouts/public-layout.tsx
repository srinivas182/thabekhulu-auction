import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import BrandLockup from '@/components/brand-lockup';
import { dashboard, home, login, register } from '@/routes';

/**
 * Shell for every public page: brand header, main content, footer.
 * Sprint 1 replaces the navigation with the approved design.
 */
export default function PublicLayout({ children }: PropsWithChildren) {
    const { auth } = usePage().props;
    const year = new Date().getFullYear();

    return (
        <div className="flex min-h-svh flex-col pt-[env(safe-area-inset-top)] pb-[env(safe-area-inset-bottom)]">
            <header className="border-b border-border bg-card">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-6 px-4 py-3 sm:px-8 sm:py-4">
                    <Link href={home()} className="text-foreground">
                        <BrandLockup className="h-8 w-auto sm:h-12" />
                    </Link>
                    <nav className="flex shrink-0 items-center gap-1 text-sm font-medium whitespace-nowrap sm:gap-2">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="rounded-md bg-primary px-3 py-2 text-primary-foreground hover:opacity-90 sm:px-4"
                            >
                                Go to your account
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="rounded-md px-3 py-2 text-foreground hover:bg-accent sm:px-4"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={register()}
                                    className="rounded-md bg-primary px-3 py-2 text-primary-foreground hover:opacity-90 sm:px-4"
                                >
                                    Register to bid
                                </Link>
                            </>
                        )}
                    </nav>
                </div>
            </header>

            <main className="flex-1">{children}</main>

            <footer className="border-t border-border bg-card">
                <div className="mx-auto flex max-w-6xl flex-col gap-2 px-5 py-8 text-sm text-muted-foreground sm:flex-row sm:justify-between sm:px-8">
                    <p>© {year} Thabekhulu Development Group</p>
                    <p>Property auctions across South Africa</p>
                </div>
            </footer>
        </div>
    );
}
