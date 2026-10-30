import SiteCredit from '@/components/site-credit';
import { Form, Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { subscribe } from '@/routes/newsletter';
import BrandLogo from '@/components/brand-logo';
import { dashboard, home, login, register } from '@/routes';

const external = (url: string) =>
    /^https?:\/\//.test(url) ||
    url.startsWith('mailto:') ||
    url.startsWith('tel:');

function NavLink({
    item,
    className,
}: {
    item: { label: string; url: string; newTab: boolean };
    className: string;
}) {
    if (external(item.url) || item.newTab) {
        return (
            <a
                href={item.url}
                className={className}
                {...(item.newTab
                    ? { target: '_blank', rel: 'noopener noreferrer' }
                    : {})}
            >
                {item.label}
            </a>
        );
    }

    return (
        <Link href={item.url} className={className}>
            {item.label}
        </Link>
    );
}

/**
 * Public shell: header and footer menus are edited in the back office
 * (Website content → Menus).
 */
export default function PublicLayout({ children }: PropsWithChildren) {
    const { auth, site } = usePage().props;
    const year = new Date().getFullYear();

    return (
        <div className="flex min-h-svh flex-col pt-[env(safe-area-inset-top)] pb-[env(safe-area-inset-bottom)]">
            <header className="border-b border-border bg-card">
                <div className="mx-auto flex max-w-[1280px] flex-wrap items-center justify-between gap-x-6 gap-y-3 px-4 py-3 sm:px-8 sm:py-4">
                    <Link href={home()} className="text-foreground">
                        <BrandLogo className="h-8 w-auto sm:h-11" />
                    </Link>
                    {site.header.length > 0 && (
                        <nav
                            aria-label="Main"
                            className="order-3 flex w-full flex-wrap gap-x-6 gap-y-1 text-[15px] font-medium md:order-none md:w-auto"
                        >
                            {site.header.map((item) => (
                                <NavLink
                                    key={item.url}
                                    item={item}
                                    className="py-1 text-foreground hover:underline"
                                />
                            ))}
                        </nav>
                    )}
                    <div className="flex shrink-0 items-center gap-1 text-sm font-medium whitespace-nowrap sm:gap-2">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="rounded-[10px] bg-primary px-3 py-2.5 text-primary-foreground hover:opacity-90 sm:px-4"
                            >
                                My account
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="rounded-[10px] px-3 py-2.5 text-foreground hover:bg-accent sm:px-4"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={register()}
                                    className="rounded-[10px] bg-primary px-3 py-2.5 text-primary-foreground hover:opacity-90 sm:px-4"
                                >
                                    Register to bid
                                </Link>
                            </>
                        )}
                    </div>
                </div>
            </header>

            <main className="flex-1">{children}</main>

            <footer className="bg-[#0e1d19] text-[#cfe3da]">
                <div className="border-b border-white/10">
                    <Form
                        action={subscribe().url}
                        method="post"
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="mx-auto flex max-w-[1280px] flex-wrap items-end gap-3 px-5 py-8 sm:px-8 [&_*]:min-w-0"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grow basis-64">
                                    <p className="font-display text-xl text-white">
                                        Auction news by email
                                    </p>
                                    <p className="text-sm">
                                        New auctions, events and results.
                                        Unsubscribe any time.
                                    </p>
                                </div>
                                <input
                                    type="text"
                                    name="website"
                                    tabIndex={-1}
                                    autoComplete="off"
                                    className="hidden"
                                    aria-hidden="true"
                                />
                                <label className="flex grow basis-56 flex-col gap-1 text-sm">
                                    Email
                                    <input
                                        name="email"
                                        type="email"
                                        className="h-11 rounded-[10px] border border-white/20 bg-white/5 px-3 text-white"
                                    />
                                </label>
                                <label className="flex basis-full items-start gap-2 text-xs sm:basis-auto">
                                    <input
                                        type="checkbox"
                                        name="consent"
                                        value="1"
                                        className="mt-0.5 size-4"
                                    />
                                    <span>
                                        I agree to receive email from Thabekhulu
                                        (
                                        <a
                                            href="/privacy"
                                            className="underline"
                                        >
                                            privacy notice
                                        </a>
                                        )
                                    </span>
                                </label>
                                <button
                                    disabled={processing}
                                    className="h-11 rounded-[10px] bg-white px-5 font-semibold text-[#0e1d19]"
                                >
                                    Subscribe
                                </button>
                                {(errors.email || errors.consent) && (
                                    <p className="basis-full text-sm text-red-300">
                                        {errors.email ?? errors.consent}
                                    </p>
                                )}
                            </>
                        )}
                    </Form>
                </div>
                <div className="mx-auto flex max-w-[1280px] flex-wrap justify-between gap-8 px-5 py-12 sm:px-8">
                    <div>
                        <p className="font-display text-2xl text-white">
                            Thabekhulu
                        </p>
                        <p className="mt-1">
                            Property auctions across South Africa
                        </p>
                        <p className="mt-4 text-sm">
                            © {year} Thabekhulu Development Group
                            {site.ffc
                                ? ` · PPRA Fidelity Fund Certificate ${site.ffc}`
                                : ''}
                        </p>
                    </div>
                    {site.footer.length > 0 && (
                        <nav
                            aria-label="Footer"
                            className="grid max-w-xl grid-cols-1 gap-x-8 gap-y-2 text-sm sm:grid-cols-2"
                        >
                            {site.footer.map((item) => (
                                <NavLink
                                    key={item.url}
                                    item={item}
                                    className="text-[#cfe3da] underline-offset-4 hover:underline"
                                />
                            ))}
                        </nav>
                    )}
                </div>
                <div className="border-t border-white/10">
                    <SiteCredit tone="onDark" />
                </div>
            </footer>
        </div>
    );
}
