import { Form, Head, router, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import verificationRoutes from '@/routes/account/verification';
import {
    address,
    identity,
    selfie,
    submit,
} from '@/routes/account/verification';

type V = {
    status: string;
    statusLabel: string;
    documentType: string | null;
    idNumberLast4: string | null;
    passportCountry: string | null;
    passportExpiresOn: string | null;
    expired: boolean;
    messages: {
        author: string;
        kind: string;
        body: string | null;
        by: string | null;
        at: string;
    }[];
    dateOfBirthInput: string | null;
    maritalStatus: string | null;
    spouseName: string | null;
    documents: string[];
    rejectionReason: string | null;
    infoRequested: string | null;
    verifiedAt: string | null;
    expiresAt: string | null;
};
type Props = {
    bankStatus: string | null;
    verification: V | null;
    maritalOptions: Record<string, string>;
    countries: Record<string, string>;
    user: { firstName: string; lastName: string };
};

const field =
    'h-12 w-full min-w-0 max-w-full rounded-[10px] border border-input bg-card px-3 text-base';
const card =
    'grid gap-5 rounded-[18px] border border-border bg-card p-6 sm:p-7 [&_*]:min-w-0';
const btn =
    'min-h-12 rounded-[10px] bg-primary px-6 font-semibold text-primary-foreground disabled:opacity-60';

function Step({
    n,
    label,
    state,
}: {
    n: number;
    label: string;
    state: 'done' | 'current' | 'todo';
}) {
    return (
        <li
            aria-current={state === 'current' ? 'step' : undefined}
            className={cn(
                'flex items-center gap-2.5 font-semibold',
                state === 'todo' ? 'text-muted-foreground' : 'text-foreground',
            )}
        >
            <span
                className={cn(
                    'inline-flex size-8 items-center justify-center rounded-full',
                    state === 'done' && 'bg-primary text-primary-foreground',
                    state === 'current' &&
                        'border-2 border-primary text-primary',
                    state === 'todo' && 'border border-input',
                )}
            >
                {state === 'done' ? (
                    <Check className="size-4" aria-hidden="true" />
                ) : (
                    n
                )}
            </span>
            {label}
        </li>
    );
}

function Done({ children }: { children: ReactNode }) {
    return (
        <p className="inline-flex items-center gap-1.5 text-sm font-semibold text-status-live">
            <Check className="size-4" aria-hidden="true" />
            {children}
        </p>
    );
}

function FileField({
    name,
    label,
    hint,
    capture,
    done,
}: {
    name: string;
    label: string;
    hint: string;
    capture?: 'user' | 'environment';
    done?: boolean;
}) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;

    return (
        <label className="flex flex-col gap-2 text-sm font-semibold">
            {label}
            <span className="flex min-h-28 flex-col items-center justify-center gap-1 rounded-[14px] border-2 border-dashed border-input bg-card p-4 text-center font-normal text-muted-foreground">
                <input
                    type="file"
                    name={name}
                    accept={capture ? 'image/*' : '.jpg,.jpeg,.png,.webp,.pdf'}
                    {...(capture ? { capture } : {})}
                    className="max-w-full text-sm text-foreground"
                />
                <span className="text-[13px]">{hint}</span>
                {done && (
                    <Done>
                        Already uploaded: choose a file only to replace it
                    </Done>
                )}
            </span>
            {errors[name] && (
                <span className="text-destructive">{errors[name]}</span>
            )}
        </label>
    );
}

export default function Verification({
    verification: v,
    bankStatus,
    maritalOptions,
    countries,
    user,
}: Props) {
    const has = (k: string) => v?.documents.includes(k) ?? false;
    const [docType, setDocType] = useState(v?.documentType ?? 'sa_id');
    const [marital, setMarital] = useState(v?.maritalStatus ?? 'single');
    const [expiry, setExpiry] = useState(v?.passportExpiresOn ?? '');
    const day = 86_400_000;
    const iso = (d: Date) => d.toISOString().slice(0, 10);
    const now = new Date();
    const tomorrow = iso(new Date(now.getTime() + day));
    const adultBy = iso(
        new Date(now.getFullYear() - 18, now.getMonth(), now.getDate()),
    );
    const left = expiry
        ? (new Date(expiry).getTime() - now.getTime()) / day
        : null;
    const expiresSoon = left !== null && left > 0 && left < 92; // about 3 months
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const locked =
        v !== null && ['submitted', 'review', 'verified'].includes(v.status);
    const identityDone =
        v?.documentType !== null &&
        v?.documentType !== undefined &&
        (has('id_front') || has('passport'));
    const ready =
        identityDone &&
        has('selfie') &&
        has('proof_of_address') &&
        !!v?.maritalStatus;
    const opts = { preserveScroll: true, forceFormData: true };

    return (
        <>
            <Head title="Verify your identity" />
            <section className="mx-auto max-w-[1280px] px-5 pt-10 pb-24 sm:px-8">
                <h1 className="font-display text-4xl sm:text-5xl">
                    Register to bid
                </h1>
                <ol
                    aria-label="Progress"
                    className="mt-6 flex flex-wrap gap-x-9 gap-y-3"
                >
                    <Step n={1} label="Your account" state="done" />
                    <Step
                        n={2}
                        label="Verify identity"
                        state={v?.status === 'verified' ? 'done' : 'current'}
                    />
                    <Step
                        n={3}
                        label="Refund bank account"
                        state={
                            bankStatus === 'verified'
                                ? 'done'
                                : v?.status === 'verified'
                                  ? 'current'
                                  : 'todo'
                        }
                    />
                    <Step
                        n={4}
                        label="Ready to bid"
                        state={
                            v?.status === 'verified' &&
                            !v.expired &&
                            bankStatus === 'verified'
                                ? 'done'
                                : 'todo'
                        }
                    />
                </ol>

                <div className="mt-9 flex flex-wrap items-start gap-10">
                    <div className="flex min-w-0 flex-[999_1_560px] flex-col gap-7">
                        {errors.verification && (
                            <p
                                role="alert"
                                className="rounded-[10px] border border-destructive px-4 py-3 text-destructive"
                            >
                                {errors.verification}
                            </p>
                        )}

                        {locked ? (
                            <section
                                className={card}
                                aria-labelledby="status-h"
                            >
                                <h2
                                    id="status-h"
                                    className="font-display text-3xl"
                                >
                                    {v!.status === 'verified'
                                        ? 'You’re verified'
                                        : 'We’re checking your details'}
                                </h2>
                                {v!.status === 'verified' ? (
                                    <p className="text-lg">
                                        Verified on {v!.verifiedAt}. Valid until{' '}
                                        {v!.expiresAt}. Next, add the bank
                                        account for deposit refunds.
                                    </p>
                                ) : (
                                    <p className="text-lg">
                                        Most buyers are approved within one
                                        working day. We’ll email you as soon as
                                        it’s done, or if we need anything else.
                                    </p>
                                )}
                                <dl className="grid gap-2 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Document
                                        </dt>
                                        <dd className="font-semibold">
                                            {v!.documentType === 'passport'
                                                ? `Passport (${v!.passportCountry})`
                                                : 'South African ID'}{' '}
                                            ending {v!.idNumberLast4}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Status
                                        </dt>
                                        <dd className="font-semibold">
                                            {v!.statusLabel}
                                        </dd>
                                    </div>
                                </dl>
                            </section>
                        ) : (
                            <>
                                {v?.infoRequested &&
                                    v.status === 'in_progress' && (
                                        <section
                                            aria-labelledby="req-h"
                                            className="grid gap-3 rounded-[14px] border border-brand-accent/30 bg-brand-accent-soft p-4"
                                        >
                                            <h2
                                                id="req-h"
                                                className="font-semibold text-brand-accent"
                                            >
                                                We need a little more from you
                                            </h2>
                                            <ol className="grid gap-2 text-sm">
                                                {v.messages
                                                    .filter((m) =>
                                                        [
                                                            'info_request',
                                                            'reply',
                                                            'document',
                                                            'submitted',
                                                        ].includes(m.kind),
                                                    )
                                                    .map((m, i) => (
                                                        <li
                                                            key={i}
                                                            className={
                                                                m.author ===
                                                                'buyer'
                                                                    ? 'ml-6 rounded-lg bg-background/70 px-3 py-2'
                                                                    : 'mr-6 rounded-lg bg-background px-3 py-2'
                                                            }
                                                        >
                                                            <span className="block text-xs text-muted-foreground">
                                                                {m.author ===
                                                                'buyer'
                                                                    ? 'You'
                                                                    : 'Thabekhulu'}{' '}
                                                                · {m.at}
                                                            </span>
                                                            {m.body}
                                                        </li>
                                                    ))}
                                            </ol>
                                            <Form
                                                action={
                                                    verificationRoutes.reply()
                                                        .url
                                                }
                                                method="post"
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                                resetOnSuccess
                                                className="grid gap-2"
                                            >
                                                {({
                                                    processing,
                                                    errors: e,
                                                }) => (
                                                    <>
                                                        <label
                                                            htmlFor="reply"
                                                            className="text-sm font-semibold"
                                                        >
                                                            Reply (optional)
                                                        </label>
                                                        <textarea
                                                            id="reply"
                                                            name="reply"
                                                            rows={3}
                                                            placeholder="For example: I’ve uploaded a clearer selfie."
                                                            className="w-full rounded-[10px] border border-input bg-background px-3 py-2 text-sm"
                                                        />
                                                        {e.reply && (
                                                            <span className="text-sm text-destructive">
                                                                {e.reply}
                                                            </span>
                                                        )}
                                                        <button
                                                            disabled={
                                                                processing
                                                            }
                                                            className="w-fit rounded-[10px] border border-input bg-background px-4 py-2 text-sm font-semibold"
                                                        >
                                                            Send reply
                                                        </button>
                                                    </>
                                                )}
                                            </Form>
                                            <p className="text-sm">
                                                Update what’s needed below, then
                                                press{' '}
                                                <strong>
                                                    Submit for verification
                                                </strong>{' '}
                                                at the bottom of the page.
                                            </p>
                                        </section>
                                    )}
                                {v?.expired && (
                                    <p
                                        role="alert"
                                        className="rounded-[10px] bg-brand-accent-soft px-4 py-3 text-brand-accent"
                                    >
                                        <strong>
                                            Your verification expired on{' '}
                                            {v.expiresAt}.
                                        </strong>{' '}
                                        Upload a current identity document and
                                        submit again to keep bidding.
                                    </p>
                                )}
                                {v?.status === 'rejected' && (
                                    <p
                                        role="alert"
                                        className="rounded-[10px] bg-muted px-4 py-3"
                                    >
                                        <strong>We couldn’t verify you:</strong>{' '}
                                        {v.rejectionReason} Please correct the
                                        details below and submit again.
                                    </p>
                                )}

                                <Form
                                    action={identity().url}
                                    method="post"
                                    options={opts}
                                    className={card}
                                    aria-labelledby="id-h"
                                >
                                    {({ processing }) => (
                                        <>
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <h2
                                                    id="id-h"
                                                    className="font-display text-3xl"
                                                >
                                                    1. Your identity document
                                                </h2>
                                                {identityDone && (
                                                    <Done>Saved</Done>
                                                )}
                                            </div>
                                            <fieldset className="flex flex-wrap gap-2.5">
                                                <legend className="mb-2 text-sm font-semibold">
                                                    Document type
                                                </legend>
                                                {[
                                                    [
                                                        'sa_id',
                                                        'South African ID',
                                                    ],
                                                    [
                                                        'passport',
                                                        'Passport (non-SA citizens)',
                                                    ],
                                                ].map(([val, label]) => (
                                                    <label
                                                        key={val}
                                                        className={cn(
                                                            'inline-flex min-h-12 items-center gap-2.5 rounded-[10px] border px-4',
                                                            docType === val
                                                                ? 'border-2 border-primary font-semibold'
                                                                : 'border-input',
                                                        )}
                                                    >
                                                        <input
                                                            type="radio"
                                                            name="document_type"
                                                            value={val}
                                                            checked={
                                                                docType === val
                                                            }
                                                            onChange={() =>
                                                                setDocType(val)
                                                            }
                                                            className="size-5 accent-primary"
                                                        />
                                                        {label}
                                                    </label>
                                                ))}
                                            </fieldset>
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                                    {docType === 'sa_id'
                                                        ? 'SA ID number'
                                                        : 'Passport number'}
                                                    <input
                                                        name="id_number"
                                                        required
                                                        inputMode={
                                                            docType === 'sa_id'
                                                                ? 'numeric'
                                                                : 'text'
                                                        }
                                                        placeholder={
                                                            v?.idNumberLast4
                                                                ? `Saved, ending ${v.idNumberLast4}`
                                                                : docType ===
                                                                    'sa_id'
                                                                  ? '13-digit ID number'
                                                                  : ''
                                                        }
                                                        className={cn(
                                                            field,
                                                            'tabular',
                                                        )}
                                                    />
                                                    <span className="font-normal text-muted-foreground">
                                                        {docType === 'sa_id'
                                                            ? 'One account per ID number. We check it against Home Affairs records.'
                                                            : 'One account per passport.'}
                                                    </span>
                                                    {errors.id_number && (
                                                        <span className="text-destructive">
                                                            {errors.id_number}
                                                        </span>
                                                    )}
                                                </label>
                                                {docType === 'passport' && (
                                                    <>
                                                        <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                                            Issuing country
                                                            <select
                                                                name="passport_country"
                                                                defaultValue={
                                                                    v?.passportCountry ??
                                                                    ''
                                                                }
                                                                className={
                                                                    field
                                                                }
                                                            >
                                                                <option value="">
                                                                    Choose a
                                                                    country
                                                                </option>
                                                                {Object.entries(
                                                                    countries,
                                                                ).map(
                                                                    ([
                                                                        code,
                                                                        name,
                                                                    ]) => (
                                                                        <option
                                                                            key={
                                                                                code
                                                                            }
                                                                            value={
                                                                                code
                                                                            }
                                                                        >
                                                                            {
                                                                                name
                                                                            }
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </select>
                                                            {errors.passport_country && (
                                                                <span className="text-destructive">
                                                                    {
                                                                        errors.passport_country
                                                                    }
                                                                </span>
                                                            )}
                                                        </label>
                                                        <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                                            Date of birth
                                                            <input
                                                                type="date"
                                                                name="date_of_birth"
                                                                defaultValue={
                                                                    v?.dateOfBirthInput ??
                                                                    ''
                                                                }
                                                                max={adultBy}
                                                                className={
                                                                    field
                                                                }
                                                            />
                                                            <span className="font-normal text-muted-foreground">
                                                                As shown in your
                                                                passport. You
                                                                must be 18 or
                                                                older.
                                                            </span>
                                                            {errors.date_of_birth && (
                                                                <span className="text-destructive">
                                                                    {
                                                                        errors.date_of_birth
                                                                    }
                                                                </span>
                                                            )}
                                                        </label>
                                                        <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                                            Passport expiry date
                                                            <input
                                                                type="date"
                                                                name="passport_expires_on"
                                                                value={expiry}
                                                                min={tomorrow}
                                                                onChange={(e) =>
                                                                    setExpiry(
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                className={
                                                                    field
                                                                }
                                                            />
                                                            {expiresSoon && (
                                                                <span className="font-normal text-amber-700 dark:text-amber-400">
                                                                    This
                                                                    passport
                                                                    expires
                                                                    within 3
                                                                    months. You
                                                                    may need a
                                                                    renewed
                                                                    passport
                                                                    before an
                                                                    auction you
                                                                    win settles.
                                                                </span>
                                                            )}
                                                            {errors.passport_expires_on && (
                                                                <span className="text-destructive">
                                                                    {
                                                                        errors.passport_expires_on
                                                                    }
                                                                </span>
                                                            )}
                                                        </label>
                                                    </>
                                                )}
                                            </div>
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <FileField
                                                    name="front"
                                                    label={
                                                        docType === 'sa_id'
                                                            ? 'Front of ID card or ID book'
                                                            : 'Passport photo page'
                                                    }
                                                    hint="JPG, PNG or PDF, up to 10 MB"
                                                    done={
                                                        has('id_front') ||
                                                        has('passport')
                                                    }
                                                />
                                                {docType === 'sa_id' && (
                                                    <FileField
                                                        name="back"
                                                        label="Back of ID card"
                                                        hint="Not needed for a green ID book"
                                                        done={has('id_back')}
                                                    />
                                                )}
                                            </div>
                                            <div>
                                                <button
                                                    disabled={processing}
                                                    className={btn}
                                                >
                                                    Save identity document
                                                </button>
                                            </div>
                                        </>
                                    )}
                                </Form>

                                <Form
                                    action={selfie().url}
                                    method="post"
                                    options={opts}
                                    className={card}
                                    aria-labelledby="selfie-h"
                                >
                                    {({ processing }) => (
                                        <>
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <h2
                                                    id="selfie-h"
                                                    className="font-display text-3xl"
                                                >
                                                    2. A selfie
                                                </h2>
                                                {has('selfie') && (
                                                    <Done>Saved</Done>
                                                )}
                                            </div>
                                            <p className="text-muted-foreground">
                                                Take a clear photo of your face
                                                in good light, without
                                                sunglasses or a hat. It’s
                                                compared with your ID photo.
                                            </p>
                                            <FileField
                                                name="selfie"
                                                label="Selfie"
                                                hint="Your phone will open the front camera"
                                                capture="user"
                                                done={has('selfie')}
                                            />
                                            <div>
                                                <button
                                                    disabled={processing}
                                                    className={btn}
                                                >
                                                    Save selfie
                                                </button>
                                            </div>
                                        </>
                                    )}
                                </Form>

                                <Form
                                    action={address().url}
                                    method="post"
                                    options={opts}
                                    className={card}
                                    aria-labelledby="addr-h"
                                >
                                    {({ processing }) => (
                                        <>
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <h2
                                                    id="addr-h"
                                                    className="font-display text-3xl"
                                                >
                                                    3. Address and marital
                                                    status
                                                </h2>
                                                {has('proof_of_address') &&
                                                    v?.maritalStatus && (
                                                        <Done>Saved</Done>
                                                    )}
                                            </div>
                                            <FileField
                                                name="proof_of_address"
                                                label="Proof of address"
                                                hint="Utility bill or bank statement, less than 3 months old"
                                                done={has('proof_of_address')}
                                            />
                                            <label className="flex max-w-md flex-col gap-1.5 text-sm font-semibold">
                                                Marital status
                                                <select
                                                    name="marital_status"
                                                    value={marital}
                                                    onChange={(e) =>
                                                        setMarital(
                                                            e.target.value,
                                                        )
                                                    }
                                                    className={field}
                                                >
                                                    {Object.entries(
                                                        maritalOptions,
                                                    ).map(([k, label]) => (
                                                        <option
                                                            key={k}
                                                            value={k}
                                                        >
                                                            {label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>
                                            {marital === 'married_icop' && (
                                                <div className="grid gap-4 rounded-[14px] bg-brand-accent-soft p-5">
                                                    <p className="text-brand-accent">
                                                        Married in community of
                                                        property? Your spouse
                                                        must consent to the
                                                        purchase before you bid.
                                                    </p>
                                                    <div className="grid gap-4 sm:grid-cols-2">
                                                        <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                                            Spouse’s full name
                                                            <input
                                                                name="spouse_name"
                                                                defaultValue={
                                                                    v?.spouseName ??
                                                                    ''
                                                                }
                                                                className={
                                                                    field
                                                                }
                                                            />
                                                            {errors.spouse_name && (
                                                                <span className="text-destructive">
                                                                    {
                                                                        errors.spouse_name
                                                                    }
                                                                </span>
                                                            )}
                                                        </label>
                                                        <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                                            Spouse’s ID number
                                                            <input
                                                                name="spouse_id_number"
                                                                inputMode="numeric"
                                                                className={cn(
                                                                    field,
                                                                    'tabular',
                                                                )}
                                                            />
                                                            {errors.spouse_id_number && (
                                                                <span className="text-destructive">
                                                                    {
                                                                        errors.spouse_id_number
                                                                    }
                                                                </span>
                                                            )}
                                                        </label>
                                                    </div>
                                                    <FileField
                                                        name="spouse_consent"
                                                        label="Spouse’s signed consent"
                                                        hint="The signed consent form, as a PDF or photo"
                                                        done={has(
                                                            'spouse_consent',
                                                        )}
                                                    />
                                                </div>
                                            )}
                                            <div>
                                                <button
                                                    disabled={processing}
                                                    className={btn}
                                                >
                                                    Save
                                                </button>
                                            </div>
                                        </>
                                    )}
                                </Form>

                                <section
                                    className="flex flex-wrap items-center justify-between gap-4 rounded-[18px] bg-brand p-6 text-brand-foreground"
                                    aria-label="Submit"
                                >
                                    <p className="max-w-xl">
                                        {ready
                                            ? `${user.firstName}, everything is ready. Submit for checking.`
                                            : 'Complete the three steps above, then submit for checking.'}
                                    </p>
                                    <button
                                        disabled={!ready}
                                        onClick={() =>
                                            router.post(
                                                submit().url,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                        className="min-h-12 rounded-[10px] bg-brand-foreground px-6 font-semibold text-brand disabled:opacity-50"
                                    >
                                        Submit for verification
                                    </button>
                                </section>
                            </>
                        )}
                    </div>

                    <aside className="flex flex-[1_1_320px] flex-col gap-4">
                        <section
                            aria-labelledby="why-h"
                            className="rounded-[18px] border border-border bg-card p-6"
                        >
                            <h2 id="why-h" className="text-lg font-semibold">
                                Why we need this
                            </h2>
                            <p className="mt-2 leading-relaxed text-muted-foreground">
                                South African law (FICA and the Consumer
                                Protection Act) requires every bidder at a
                                property auction to be identified before
                                bidding.
                            </p>
                        </section>
                        <section
                            aria-labelledby="safe-h"
                            className="rounded-[18px] border border-border bg-card p-6"
                        >
                            <h2 id="safe-h" className="text-lg font-semibold">
                                How we protect it
                            </h2>
                            <p className="mt-2 leading-relaxed text-muted-foreground">
                                Your documents are encrypted and seen only by
                                our verification team. We never show your name
                                to other bidders.{' '}
                                <a href="/privacy" className="underline">
                                    Privacy notice (POPIA)
                                </a>
                            </p>
                        </section>
                        <section
                            aria-labelledby="time-h"
                            className="rounded-[18px] bg-brand p-6 text-brand-foreground"
                        >
                            <h2 id="time-h" className="text-lg font-semibold">
                                How long it takes
                            </h2>
                            <p className="mt-2 leading-relaxed opacity-85">
                                Most buyers are approved within one working day.
                                We’ll email you if we need anything else.
                            </p>
                        </section>
                    </aside>
                </div>
            </section>
        </>
    );
}
