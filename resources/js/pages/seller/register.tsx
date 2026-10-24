import { Form, Head } from '@inertiajs/react';
import { store } from '@/routes/seller/register';

const field =
    'h-12 w-full min-w-0 rounded-[10px] border border-input bg-card px-3 text-base';

export default function SellerRegister() {
    return (
        <>
            <Head title="Sell with Thabekhulu" />
            <section className="mx-auto max-w-[640px] px-5 py-12 sm:px-8">
                <h1 className="font-display text-4xl">Sell with Thabekhulu</h1>
                <p className="mt-3 text-lg text-muted-foreground">
                    Create a seller account to submit your property. Our team
                    reviews every submission before anything is listed, and your
                    identity is never shown publicly.
                </p>
                <Form
                    {...store.form()}
                    className="mt-8 grid gap-4 rounded-[18px] border border-border bg-card p-6 [&_*]:min-w-0"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                    First name
                                    <input
                                        name="first_name"
                                        className={field}
                                    />
                                    {errors.first_name && (
                                        <span className="text-destructive">
                                            {errors.first_name}
                                        </span>
                                    )}
                                </label>
                                <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                    Last name
                                    <input name="last_name" className={field} />
                                    {errors.last_name && (
                                        <span className="text-destructive">
                                            {errors.last_name}
                                        </span>
                                    )}
                                </label>
                            </div>
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
                                Mobile
                                <input
                                    name="mobile"
                                    inputMode="tel"
                                    className={field}
                                />
                                {errors.mobile && (
                                    <span className="text-destructive">
                                        {errors.mobile}
                                    </span>
                                )}
                            </label>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                    Password
                                    <input
                                        name="password"
                                        type="password"
                                        autoComplete="new-password"
                                        className={field}
                                    />
                                    {errors.password && (
                                        <span className="text-destructive">
                                            {errors.password}
                                        </span>
                                    )}
                                </label>
                                <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                    Confirm password
                                    <input
                                        name="password_confirmation"
                                        type="password"
                                        autoComplete="new-password"
                                        className={field}
                                    />
                                </label>
                            </div>
                            <label className="flex items-start gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="popia"
                                    value="1"
                                    className="mt-0.5 size-4 accent-primary"
                                />
                                <span>
                                    I agree that Thabekhulu may process my
                                    details to assess and market my property.{' '}
                                    <a href="/privacy" className="underline">
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
                                Create seller account
                            </button>
                        </>
                    )}
                </Form>
            </section>
        </>
    );
}
