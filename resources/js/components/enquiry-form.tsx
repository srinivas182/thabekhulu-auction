import { Form, usePage } from '@inertiajs/react';
import { area, field, FormField, Honeypot } from '@/components/public-form';

/** Contact form; also used for questions about a specific listing. */
export default function EnquiryForm({
    listing,
    defaultSubject,
}: {
    listing?: string;
    defaultSubject?: string;
}) {
    const sent = Boolean(
        (usePage() as unknown as { flash?: { submitted?: boolean } }).flash
            ?.submitted,
    );

    if (sent) {
        return (
            <p
                role="status"
                className="rounded-[14px] bg-status-live/10 p-5 font-semibold text-status-live"
            >
                Thank you for your message. We’ll reply by email.
            </p>
        );
    }

    return (
        <Form
            action="/enquiries"
            method="post"
            resetOnSuccess
            className="relative grid max-w-2xl gap-4 rounded-[18px] border border-border bg-card p-6"
        >
            {({ processing, errors }) => (
                <>
                    <Honeypot />
                    {listing && (
                        <input type="hidden" name="listing" value={listing} />
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField label="Your name" error={errors.name}>
                            <input
                                name="name"
                                required
                                autoComplete="name"
                                className={field}
                            />
                        </FormField>
                        <FormField label="Email address" error={errors.email}>
                            <input
                                name="email"
                                type="email"
                                required
                                autoComplete="email"
                                className={field}
                            />
                        </FormField>
                    </div>
                    <FormField label="Phone (optional)" error={errors.phone}>
                        <input
                            name="phone"
                            type="tel"
                            autoComplete="tel"
                            className={field}
                        />
                    </FormField>
                    <FormField label="Subject" error={errors.subject}>
                        <input
                            name="subject"
                            required
                            defaultValue={defaultSubject}
                            className={field}
                        />
                    </FormField>
                    <FormField label="Message" error={errors.message}>
                        <textarea name="message" required className={area} />
                    </FormField>
                    <button
                        disabled={processing}
                        className="h-12 w-fit rounded-[10px] bg-primary px-6 font-semibold text-primary-foreground disabled:opacity-60"
                    >
                        Send message
                    </button>
                </>
            )}
        </Form>
    );
}
