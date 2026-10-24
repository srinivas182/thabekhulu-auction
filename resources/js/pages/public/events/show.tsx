import { Form, Head, Link } from '@inertiajs/react';
import { randCents } from '@/lib/cents';
import { order } from '@/routes/events';

type Props = {
    event: {
        slug: string;
        title: string;
        kind: string;
        venue: string | null;
        price: number;
        when: string;
        left: number;
        description: string | null;
        status: string;
        started: boolean;
        maxPerOrder: number;
    };
    canPay: boolean;
};
const field =
    'h-12 w-full min-w-0 rounded-[10px] border border-input bg-card px-3 text-base';

export default function EventShow({ event: e, canPay }: Props) {
    const open = e.status === 'published' && !e.started && e.left > 0 && canPay;

    return (
        <>
            <Head title={e.title} />
            <section className="mx-auto grid max-w-[1100px] gap-10 px-5 py-12 sm:px-8 lg:grid-cols-[1fr_380px]">
                <div className="min-w-0">
                    <Link href="/events" className="text-sm underline">
                        Events
                    </Link>
                    <h1 className="mt-1 font-display text-4xl sm:text-5xl">
                        {e.title}
                    </h1>
                    <p className="mt-3 text-lg">
                        {e.when} SAST ·{' '}
                        {e.kind === 'online'
                            ? 'Online (the link is on your ticket)'
                            : e.venue}
                    </p>
                    {e.description && (
                        <div
                            className="mt-6 max-w-none leading-relaxed [&_p]:mt-3"
                            dangerouslySetInnerHTML={{ __html: e.description }}
                        />
                    )}
                </div>
                <aside
                    aria-labelledby="reg-h"
                    className="grid h-fit gap-4 rounded-[18px] border border-border bg-card p-6 [&_*]:min-w-0"
                >
                    <h2 id="reg-h" className="font-display text-2xl">
                        {e.price === 0
                            ? 'Register (free)'
                            : `Tickets: ${randCents(e.price)} each`}
                    </h2>
                    {e.status === 'cancelled' ? (
                        <p className="rounded-[10px] bg-muted p-3">
                            This event has been cancelled.
                        </p>
                    ) : e.started ? (
                        <p className="rounded-[10px] bg-muted p-3">
                            This event has started.
                        </p>
                    ) : e.left === 0 ? (
                        <p className="rounded-[10px] bg-muted p-3">
                            This event is full.
                        </p>
                    ) : !canPay ? (
                        <p className="rounded-[10px] bg-muted p-3">
                            Ticket sales open soon.
                        </p>
                    ) : null}
                    {open && (
                        <Form
                            action={order(e.slug).url}
                            method="post"
                            className="grid gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="text"
                                        name="website"
                                        tabIndex={-1}
                                        autoComplete="off"
                                        className="hidden"
                                        aria-hidden="true"
                                    />
                                    <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                        Full name
                                        <input name="name" className={field} />
                                        {errors.name && (
                                            <span className="text-destructive">
                                                {errors.name}
                                            </span>
                                        )}
                                    </label>
                                    <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                        Email
                                        <input
                                            name="email"
                                            type="email"
                                            className={field}
                                        />
                                        {errors.email && (
                                            <span className="text-destructive">
                                                {errors.email}
                                            </span>
                                        )}
                                    </label>
                                    <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                        Mobile (optional)
                                        <input
                                            name="mobile"
                                            inputMode="tel"
                                            className={field}
                                        />
                                    </label>
                                    <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                        Tickets
                                        <select
                                            name="quantity"
                                            className={field}
                                            defaultValue="1"
                                        >
                                            {Array.from(
                                                {
                                                    length: Math.min(
                                                        e.maxPerOrder,
                                                        e.left,
                                                    ),
                                                },
                                                (_, i) => (
                                                    <option
                                                        key={i}
                                                        value={i + 1}
                                                    >
                                                        {i + 1}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        {errors.quantity && (
                                            <span className="text-destructive">
                                                {errors.quantity}
                                            </span>
                                        )}
                                    </label>
                                    <label className="flex items-start gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="popia"
                                            value="1"
                                            className="mt-0.5 size-4 accent-primary"
                                        />
                                        <span>
                                            I agree that Thabekhulu may use my
                                            details to manage this event.{' '}
                                            <a
                                                href="/privacy"
                                                className="underline"
                                            >
                                                Privacy notice
                                            </a>
                                        </span>
                                    </label>
                                    {errors.popia && (
                                        <span className="text-sm text-destructive">
                                            {errors.popia}
                                        </span>
                                    )}
                                    <button
                                        disabled={processing}
                                        className="min-h-12 rounded-[10px] bg-primary px-6 font-semibold text-primary-foreground"
                                    >
                                        {e.price === 0
                                            ? 'Register'
                                            : 'Continue to payment'}
                                    </button>
                                    <p className="text-xs text-muted-foreground">
                                        {e.left} places left.{' '}
                                        {e.price > 0
                                            ? 'Places are held for 15 minutes while you pay.'
                                            : ''}
                                    </p>
                                </>
                            )}
                        </Form>
                    )}
                </aside>
            </section>
        </>
    );
}
