import { Form, Head, usePage } from '@inertiajs/react';
import { area, field, FormField, Honeypot } from '@/components/public-form';

type Props = { types: { value: string; label: string }[] };

export default function SellWithUs({ types }: Props) {
    const sent = Boolean(
        (usePage() as unknown as { flash?: { submitted?: boolean } }).flash
            ?.submitted,
    );

    return (
        <>
            <Head title="Sell your property at auction | Thabekhulu">
                <meta
                    name="description"
                    content="Tell us about your home, land, mine or commercial property. Thabekhulu will contact you about renovation, pricing and auction."
                />
            </Head>
            <section className="mx-auto grid max-w-[1280px] gap-10 px-5 py-12 sm:px-8 sm:py-16 lg:grid-cols-[1fr_1.2fr]">
                <div>
                    <h1 className="font-display text-4xl leading-tight sm:text-5xl">
                        Sell your property at auction
                    </h1>
                    <p className="mt-4 text-lg text-muted-foreground">
                        Tell us about your home, land, mine or commercial
                        property. Our team will call you within two working days
                        to talk about renovation, pricing and how an auction
                        would work for you.
                    </p>
                    <ul className="mt-6 grid gap-3 text-[17px]">
                        <li className="border-l-2 border-primary pl-4">
                            Open bidding from verified buyers
                        </li>
                        <li className="border-l-2 border-primary pl-4">
                            Option for Thabekhulu to renovate before sale
                        </li>
                        <li className="border-l-2 border-primary pl-4">
                            Every document and fee agreed before listing
                        </li>
                    </ul>
                </div>
                {sent ? (
                    <p
                        role="status"
                        className="h-fit rounded-[18px] bg-status-live/10 p-6 text-lg font-semibold text-status-live"
                    >
                        Thank you. Our team will contact you within two working
                        days.
                    </p>
                ) : (
                    <Form
                        action="/sell-with-us"
                        method="post"
                        encType="multipart/form-data"
                        className="relative grid h-fit gap-4 rounded-[18px] border border-border bg-card p-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Honeypot />
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        label="Your name"
                                        error={errors.name}
                                    >
                                        <input
                                            name="name"
                                            required
                                            autoComplete="name"
                                            className={field}
                                        />
                                    </FormField>
                                    <FormField
                                        label="Mobile number"
                                        error={errors.mobile}
                                    >
                                        <input
                                            name="mobile"
                                            type="tel"
                                            required
                                            autoComplete="tel"
                                            placeholder="082 123 4567"
                                            className={field}
                                        />
                                    </FormField>
                                </div>
                                <FormField
                                    label="Email address"
                                    error={errors.email}
                                >
                                    <input
                                        name="email"
                                        type="email"
                                        required
                                        autoComplete="email"
                                        className={field}
                                    />
                                </FormField>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        label="Type of property"
                                        error={errors.property_type}
                                    >
                                        <select
                                            name="property_type"
                                            required
                                            className={field}
                                            defaultValue=""
                                        >
                                            <option value="" disabled>
                                                Choose…
                                            </option>
                                            {types.map((t) => (
                                                <option
                                                    key={t.value}
                                                    value={t.value}
                                                >
                                                    {t.label}
                                                </option>
                                            ))}
                                        </select>
                                    </FormField>
                                    <FormField
                                        label="Town or city"
                                        error={errors.location}
                                    >
                                        <input
                                            name="location"
                                            required
                                            className={field}
                                        />
                                    </FormField>
                                </div>
                                <FormField
                                    label="Asking price (R, optional)"
                                    error={errors.asking_price}
                                >
                                    <input
                                        name="asking_price"
                                        inputMode="numeric"
                                        className={field}
                                    />
                                </FormField>
                                <FormField
                                    label="About the property"
                                    error={errors.description}
                                    hint="Size, condition, documents you have (title deed, rights, leases)."
                                >
                                    <textarea
                                        name="description"
                                        required
                                        className={area}
                                    />
                                </FormField>
                                <FormField
                                    label="Photos (optional, up to 10)"
                                    error={errors.photos ?? errors['photos.0']}
                                    hint="Seen only by the Thabekhulu team."
                                >
                                    <input
                                        name="photos[]"
                                        type="file"
                                        multiple
                                        accept=".jpg,.jpeg,.png,.webp"
                                        className="text-sm font-normal"
                                    />
                                </FormField>
                                <label className="flex items-start gap-3 text-sm leading-relaxed">
                                    <input
                                        type="checkbox"
                                        name="consent"
                                        value="1"
                                        className="mt-0.5 size-5 accent-primary"
                                    />
                                    <span>
                                        I agree that Thabekhulu may contact me
                                        about selling this property and process
                                        my details as described in the{' '}
                                        <a
                                            href="/privacy"
                                            className="underline"
                                        >
                                            {' '}
                                            privacy notice{' '}
                                        </a>
                                        .
                                    </span>
                                </label>
                                {errors.consent && (
                                    <p className="text-sm font-semibold text-destructive">
                                        {errors.consent}
                                    </p>
                                )}
                                <button
                                    disabled={processing}
                                    className="h-12 rounded-[10px] bg-primary font-semibold text-primary-foreground disabled:opacity-60"
                                >
                                    Send to Thabekhulu
                                </button>
                            </>
                        )}
                    </Form>
                )}
            </section>
        </>
    );
}
