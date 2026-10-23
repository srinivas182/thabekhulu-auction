import { Form, Head } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import { store } from '@/routes/account/entities';

type E = {
    ulid: string;
    type: string;
    name: string;
    registration: string;
    status: string;
    rejectionReason: string | null;
    controllers: number;
};
type Props = {
    entities: E[];
    documents: Record<'company' | 'trust', Record<string, string>>;
};

const field =
    'h-12 w-full min-w-0 rounded-[10px] border border-input bg-card px-3 text-base';
const status: Record<string, string> = {
    pending: 'Being verified',
    verified: 'Verified',
    rejected: 'Not verified',
};

export default function Entities({ entities, documents }: Props) {
    const [type, setType] = useState<'company' | 'trust'>('company');
    const [people, setPeople] = useState([0]);

    return (
        <>
            <Head title="Companies and trusts" />
            <section className="mx-auto max-w-[900px] px-5 py-12 sm:px-8">
                <h1 className="font-display text-4xl">Companies and trusts</h1>
                <p className="mt-3 text-lg text-muted-foreground">
                    To bid for a company or trust, add it here first. We verify
                    its registration, the people who control it, and your
                    authority to act for it.
                </p>

                {entities.length > 0 && (
                    <ul className="mt-8 divide-y divide-border rounded-[18px] border border-border bg-card">
                        {entities.map((e) => (
                            <li
                                key={e.ulid}
                                className="flex flex-wrap items-center justify-between gap-3 px-5 py-4"
                            >
                                <div>
                                    <p className="font-semibold">{e.name}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {e.type === 'company'
                                            ? 'Company'
                                            : 'Trust'}{' '}
                                        · {e.registration} · {e.controllers}{' '}
                                        people listed
                                    </p>
                                    {e.rejectionReason && (
                                        <p className="text-sm text-destructive">
                                            {e.rejectionReason}
                                        </p>
                                    )}
                                </div>
                                <span
                                    className={cn(
                                        'text-sm font-semibold',
                                        e.status === 'verified'
                                            ? 'text-status-live'
                                            : e.status === 'rejected'
                                              ? 'text-destructive'
                                              : 'text-status-closing',
                                    )}
                                >
                                    {status[e.status]}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}

                <Form
                    {...store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="mt-8 grid gap-5 rounded-[18px] border border-border bg-card p-6 [&_*]:min-w-0"
                >
                    {({ processing, errors }) => (
                        <>
                            <h2 className="font-display text-2xl">
                                Add a company or trust
                            </h2>
                            <fieldset className="flex flex-wrap gap-2.5">
                                <legend className="mb-2 text-sm font-semibold">
                                    Type
                                </legend>
                                {(['company', 'trust'] as const).map((t) => (
                                    <label
                                        key={t}
                                        className={cn(
                                            'inline-flex min-h-12 items-center gap-2.5 rounded-[10px] border px-4',
                                            type === t
                                                ? 'border-2 border-primary font-semibold'
                                                : 'border-input',
                                        )}
                                    >
                                        <input
                                            type="radio"
                                            name="type"
                                            value={t}
                                            checked={type === t}
                                            onChange={() => setType(t)}
                                            className="size-5 accent-primary"
                                        />
                                        {t === 'company'
                                            ? 'Company (CIPC registered)'
                                            : 'Trust'}
                                    </label>
                                ))}
                            </fieldset>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                    {type === 'company'
                                        ? 'Registered name'
                                        : 'Trust name'}
                                    <input name="name" className={field} />
                                    {errors.name && (
                                        <span className="text-destructive">
                                            {errors.name}
                                        </span>
                                    )}
                                </label>
                                <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                    {type === 'company'
                                        ? 'Registration number'
                                        : 'Master’s Office reference'}
                                    <input
                                        name="registration_number"
                                        placeholder={
                                            type === 'company'
                                                ? '2015/123456/07'
                                                : 'IT 1234/2015'
                                        }
                                        className={field}
                                    />
                                    {errors.registration_number && (
                                        <span className="text-destructive">
                                            {errors.registration_number}
                                        </span>
                                    )}
                                </label>
                            </div>

                            <fieldset className="grid gap-3">
                                <legend className="mb-1 text-sm font-semibold">
                                    {type === 'company'
                                        ? 'Directors and everyone owning 25% or more'
                                        : 'Trustees and beneficiaries with control'}
                                </legend>
                                {people.map((key, i) => (
                                    <div
                                        key={key}
                                        className="grid gap-2 rounded-[14px] border border-border p-3 sm:grid-cols-[1.4fr_1fr_1.2fr_0.7fr_auto] sm:items-end"
                                    >
                                        <label className="flex flex-col gap-1 text-xs font-semibold">
                                            Full name
                                            <input
                                                name={`controllers[${i}][name]`}
                                                className={field}
                                            />
                                        </label>
                                        <label className="flex flex-col gap-1 text-xs font-semibold">
                                            Role
                                            <input
                                                name={`controllers[${i}][role]`}
                                                placeholder={
                                                    type === 'company'
                                                        ? 'Director'
                                                        : 'Trustee'
                                                }
                                                className={field}
                                            />
                                        </label>
                                        <label className="flex flex-col gap-1 text-xs font-semibold">
                                            ID or passport number
                                            <input
                                                name={`controllers[${i}][id_number]`}
                                                className={field}
                                            />
                                            {errors[
                                                `controllers.${i}.id_number`
                                            ] && (
                                                <span className="text-destructive">
                                                    {
                                                        errors[
                                                            `controllers.${i}.id_number`
                                                        ]
                                                    }
                                                </span>
                                            )}
                                        </label>
                                        <label className="flex flex-col gap-1 text-xs font-semibold">
                                            Owns %
                                            <input
                                                name={`controllers[${i}][ownership]`}
                                                inputMode="decimal"
                                                className={field}
                                            />
                                        </label>
                                        <button
                                            type="button"
                                            aria-label="Remove person"
                                            disabled={people.length === 1}
                                            onClick={() =>
                                                setPeople(
                                                    people.filter(
                                                        (_, k) => k !== i,
                                                    ),
                                                )
                                            }
                                            className="inline-flex size-12 items-center justify-center rounded-[10px] border border-input disabled:opacity-40"
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </div>
                                ))}
                                {errors.controllers && (
                                    <p className="text-sm text-destructive">
                                        {errors.controllers}
                                    </p>
                                )}
                                <button
                                    type="button"
                                    onClick={() =>
                                        setPeople([
                                            ...people,
                                            Math.max(...people) + 1,
                                        ])
                                    }
                                    className="inline-flex w-fit items-center gap-1.5 text-sm font-semibold underline"
                                >
                                    <Plus className="size-4" />
                                    Add another person
                                </button>
                            </fieldset>

                            <div className="grid gap-4 sm:grid-cols-2">
                                {Object.entries(documents[type]).map(
                                    ([kind, label]) => (
                                        <label
                                            key={kind}
                                            className="flex flex-col gap-1.5 text-sm font-semibold"
                                        >
                                            {label}
                                            <input
                                                type="file"
                                                name={`documents[${kind}]`}
                                                accept=".pdf,.jpg,.jpeg,.png"
                                                className="text-sm font-normal"
                                            />
                                            {errors[`documents.${kind}`] && (
                                                <span className="text-destructive">
                                                    {
                                                        errors[
                                                            `documents.${kind}`
                                                        ]
                                                    }
                                                </span>
                                            )}
                                        </label>
                                    ),
                                )}
                            </div>
                            <button
                                disabled={processing}
                                className="min-h-12 w-fit rounded-[10px] bg-primary px-6 font-semibold text-primary-foreground disabled:opacity-60"
                            >
                                Submit for verification
                            </button>
                        </>
                    )}
                </Form>
            </section>
        </>
    );
}
