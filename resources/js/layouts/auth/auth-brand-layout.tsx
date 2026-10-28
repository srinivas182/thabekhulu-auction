import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';

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

/** Registration layout: a real property photo, the buyer journey with step one current, and the form. */
export default function AuthBrandLayout({
    children,
    title,
    description,
}: {
    children: React.ReactNode;
    title: string;
    description: string;
}) {
    const cover = (usePage().props as { cover?: string | null }).cover ?? null;

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
                            Register once, bid on any auction.
                        </p>
                        <p className="mt-3 text-[#cfe3da] lg:hidden">
                            Step 1 of 4: create your account.
                        </p>
                    </div>

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
                                        className={current ? '' : 'opacity-75'}
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

                    <p className="hidden border-t border-white/15 pt-5 text-sm text-[#cfe3da] lg:block">
                        Deposits are refunded to bidders who don’t win, to the
                        bank account you verify.
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
