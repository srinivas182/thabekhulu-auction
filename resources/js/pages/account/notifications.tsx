import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { update } from '@/routes/account/notifications';

type Props = {
    whatsapp: { optedIn: boolean; number: string | null; available: boolean };
};
const field =
    'h-12 w-full min-w-0 rounded-[10px] border border-input bg-card px-3 text-base';

export default function Notifications({ whatsapp }: Props) {
    const [on, setOn] = useState(whatsapp.optedIn);

    return (
        <>
            <Head title="Auction alerts" />
            <section className="mx-auto max-w-[760px] px-5 py-12 sm:px-8">
                <h1 className="font-display text-4xl">Auction alerts</h1>
                <p className="mt-3 text-lg text-muted-foreground">
                    We always email you when you’re outbid, when an auction
                    you’re in is about to close, and when it closes. You can
                    also get these on WhatsApp.
                </p>
                <Form
                    {...update.form()}
                    options={{ preserveScroll: true }}
                    className="mt-8 grid gap-4 rounded-[18px] border border-border bg-card p-6 [&_*]:min-w-0"
                >
                    {({ processing, errors }) => (
                        <>
                            <label className="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    name="whatsapp"
                                    value="1"
                                    checked={on}
                                    onChange={(e) => setOn(e.target.checked)}
                                    className="mt-1 size-5 accent-primary"
                                />
                                <span>
                                    <strong>
                                        Send my auction alerts on WhatsApp too.
                                    </strong>
                                    <br />
                                    <span className="text-sm text-muted-foreground">
                                        I agree that Thabekhulu may send me
                                        auction alerts (outbid, closing soon,
                                        results) on WhatsApp at this number. I
                                        can switch this off here at any time.
                                        Messages are sent through Meta’s
                                        WhatsApp Business service.
                                    </span>
                                </span>
                            </label>
                            <input
                                type="hidden"
                                name="whatsapp"
                                value={on ? '1' : '0'}
                            />
                            {on && (
                                <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                    WhatsApp number
                                    <input
                                        name="number"
                                        inputMode="tel"
                                        defaultValue={whatsapp.number ?? ''}
                                        placeholder="+27 82 555 0000"
                                        className={field}
                                    />
                                    {errors.number && (
                                        <span className="text-destructive">
                                            {errors.number}
                                        </span>
                                    )}
                                </label>
                            )}
                            {!whatsapp.available && (
                                <p className="rounded-[10px] bg-muted p-3 text-sm">
                                    WhatsApp alerts are being set up. Your
                                    choice is saved and will take effect as soon
                                    as they start.
                                </p>
                            )}
                            <button
                                disabled={processing}
                                className="min-h-12 w-fit rounded-[10px] bg-primary px-6 font-semibold text-primary-foreground"
                            >
                                Save
                            </button>
                        </>
                    )}
                </Form>
            </section>
        </>
    );
}
