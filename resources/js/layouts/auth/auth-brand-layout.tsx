import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';

export type AuthPanel = 'register' | 'login' | 'account' | 'staff';

const journey = [
    {
        title: 'Create your account',
        text: 'Your name, email address and mobile number.',
    },
    {
        title: 'Verify your identity',
        text: 'Your ID and a quick selfie, once.',
    },
    {
        title: 'Get approved and pay the deposit',
        text: 'For each auction you want to join.',
    },
    { title: 'Bid', text: 'Live online, or by sealed tender.' },
];

const panels: Record<
    Exclude<AuthPanel, 'register'>,
    { headline: string; text: string; points: string[]; footnote: string }
> = {
    login: {
        headline: 'Welcome back.',
        text: 'Sign in to follow your auctions, bids and deposits in one place.',
        points: [
            'Live bids and closing times',
            'Deposit and refund status',
            'Documents for the auctions you’re approved for',
        ],
        footnote: 'Bids are binding, so keep your password to yourself.',
    },
    account: {
        headline: 'Your account, kept secure.',
        text: 'Passwords, codes and email checks make sure only you can bid with your account.',
        points: [
            'Reset links can be used once',
            'Codes protect large bids',
            'Every sign-in is recorded',
        ],
        footnote: 'Need help? Use the Contact page and we’ll get back to you.',
    },
    staff: {
        headline: 'Welcome to the Thabekhulu back office.',
        text: 'Choose a password to accept your invitation. You’ll only see the parts of the back office your role needs.',
        points: [
            'Listings, bidders and deposits',
            'Settlement and reports',
            'Every action is recorded',
        ],
        footnote: 'Invitation links can be used once.',
    },
};

/** Sign-in layout: a real property photo under the brand green, panel content for the page's purpose, and the form. */
export default function AuthBrandLayout({
    children,
    title,
    description,
    panel = 'register',
}: {
    children: React.ReactNode;
    title: string;
    description: string;
    panel?: AuthPanel;
}) {
    const cover = (usePage().props as { cover?: string | null }).cover ?? null;
    const content = panel === 'register' ? null : panels[panel];

    return (
        <div className="grid min-h-svh bg-background lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
            <aside className="relative overflow-hidden bg-[#14352b] text-white lg:sticky lg:top-0 lg:h-svh">
                {cover && (
                    <img
                        src={cover}
                        alt=""
                        className="absolute inset-0 size-full object-cover opacity-30"
                    />
                )}
                <div
                    className="absolute inset-0 bg-gradient-to-b from-[#14352b]/40 via-[#14352b]/70 to-[#0e1d19]"
                    aria-hidden="true"
                />
                <div className="relative flex h-full flex-col gap-8 px-6 py-7 sm:px-10 lg:px-14 lg:py-12">
                    <Link
                        href={home()}
                        className="flex w-fit items-center gap-3 rounded-md focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white"
                    >
                        <AppLogoIcon className="size-9 fill-current text-white" />
                        <span className="font-display text-2xl leading-none">
                            Thabekhulu
                        </span>
                    </Link>

                    <div className="lg:mt-auto">
                        <p className="font-display text-[34px] leading-[1.05] sm:text-5xl lg:text-[56px]">
                            {content
                                ? content.headline
                                : 'Register once, bid on any auction.'}
                        </p>
                        {content ? (
                            <p className="mt-3 text-[#cfe3da] lg:max-w-md lg:text-lg">
                                {content.text}
                            </p>
                        ) : (
                            <p className="mt-3 text-[#cfe3da] lg:hidden">
                                Step 1 of 4: create your account.
                            </p>
                        )}
                    </div>

                    {content ? (
                        <ul className="hidden gap-3 lg:grid">
                            {content.points.map((point) => (
                                <li
                                    key={point}
                                    className="flex items-center gap-3 border-t border-white/15 pt-3 text-[#e7f1ec]"
                                >
                                    <span
                                        className="size-1.5 shrink-0 rounded-full bg-white/70"
                                        aria-hidden="true"
                                    />
                                    {point}
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <ol
                            className="hidden gap-0 lg:grid"
                            aria-label="How you get to your first bid"
                        >
                            {journey.map((step, i) => {
                                const current = i === 0;

                                return (
                                    <li
                                        key={step.title}
                                        className="relative grid grid-cols-[2rem_1fr] gap-4 pb-6 last:pb-0"
                                    >
                                        {i < journey.length - 1 && (
                                            <span
                                                className="absolute top-8 bottom-0 left-4 w-px bg-white/25"
                                                aria-hidden="true"
                                            />
                                        )}
                                        <span
                                            className={
                                                current
                                                    ? 'relative flex size-8 items-center justify-center rounded-full bg-white text-sm font-semibold text-[#14352b]'
                                                    : 'relative flex size-8 items-center justify-center rounded-full border border-white/40 text-sm text-white/80'
                                            }
                                        >
                                            {i + 1}
                                        </span>
                                        <div
                                            className={
                                                current ? '' : 'opacity-75'
                                            }
                                        >
                                            <p className="font-semibold">
                                                {step.title}
                                                {current && (
                                                    <span className="ml-2 rounded-full bg-white/15 px-2 py-0.5 text-xs font-medium">
                                                        You’re here
                                                    </span>
                                                )}
                                            </p>
                                            <p className="text-sm text-[#cfe3da]">
                                                {step.text}
                                            </p>
                                        </div>
                                    </li>
                                );
                            })}
                        </ol>
                    )}

                    <p className="hidden border-t border-white/15 pt-5 text-sm text-[#cfe3da] lg:block">
                        {content
                            ? content.footnote
                            : 'Deposits are refunded to bidders who don’t win, to the bank account you verify.'}
                    </p>
                </div>
            </aside>

            <main className="flex items-start justify-center px-6 py-10 sm:px-10 lg:items-center lg:py-16">
                <div className="w-full max-w-[460px]">
                    <h1 className="font-display text-[34px] leading-tight sm:text-[40px]">
                        {title}
                    </h1>
                    {description && (
                        <p className="mt-2 text-muted-foreground">
                            {description}
                        </p>
                    )}
                    <div className="mt-8">{children}</div>
                </div>
            </main>
        </div>
    );
}
