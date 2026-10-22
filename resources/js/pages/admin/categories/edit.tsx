import { Head, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, update } from '@/routes/admin/categories';
import documents from '@/routes/admin/categories/documents';
import fieldsRoutes from '@/routes/admin/categories/fields';

type Option = { value: string; label: string };
type Field = {
    id: number;
    key: string;
    label: string;
    type: string;
    unit: string | null;
    options: string[];
    required: boolean;
    onCard: boolean;
    keyFacts: boolean;
    filterable: boolean;
};
type DocType = {
    id: number;
    name: string;
    mandatory: boolean;
    visibility: string;
    formats: string[];
    maxSizeMb: number;
};
type Category = {
    slug: string;
    name: string;
    description: string | null;
    icon: string | null;
    defaultDeposit: number;
    defaultFeePercent: number;
    defaultDepositTerms: string;
    requiresComplianceNotice: boolean;
    visible: boolean;
    seoTitle: string | null;
    seoDescription: string | null;
};
type Props = {
    category: Category;
    fields: Field[];
    documents: DocType[];
    options: {
        types: Option[];
        visibilities: Option[];
        depositTerms: Option[];
        formats: string[];
    };
};

const select = 'h-9 rounded-md border border-input bg-background px-3 text-sm';
const opts = { preserveScroll: true };

function Check({
    label,
    checked,
    onChange,
}: {
    label: string;
    checked: boolean;
    onChange: (v: boolean) => void;
}) {
    return (
        <label className="flex items-center gap-2 text-sm">
            <input
                type="checkbox"
                className="size-4 accent-primary"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
            />
            {label}
        </label>
    );
}

function FieldEditor({
    slug,
    field,
    types,
    isFirst,
    isLast,
}: {
    slug: string;
    field?: Field;
    types: Option[];
    isFirst?: boolean;
    isLast?: boolean;
}) {
    const [f, setF] = useState({
        label: field?.label ?? '',
        type: field?.type ?? 'number',
        unit: field?.unit ?? '',
        options: (field?.options ?? []).join('\n'),
        required: field?.required ?? false,
        on_card: field?.onCard ?? false,
        key_facts: field?.keyFacts ?? true,
        filterable: field?.filterable ?? false,
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const save = () => {
        const done = {
            ...opts,
            onError: setErrors,
            onSuccess: () => {
                setErrors({});
                if (!field) setF({ ...f, label: '', unit: '', options: '' });
            },
        };
        if (field)
            router.put(
                fieldsRoutes.update({ category: slug, field: field.id }).url,
                f,
                done,
            );
        else router.post(fieldsRoutes.store(slug).url, f, done);
    };

    return (
        <div className="grid gap-3 border-b border-border px-5 py-4 last:border-b-0">
            <div className="flex flex-wrap items-end gap-3">
                <div className="grid gap-1.5">
                    <Label htmlFor={`label-${field?.id ?? 'new'}`}>
                        {field ? 'Label' : 'New field'}
                    </Label>
                    <Input
                        id={`label-${field?.id ?? 'new'}`}
                        value={f.label}
                        onChange={(e) => setF({ ...f, label: e.target.value })}
                        className="w-56"
                        placeholder="e.g. Stands"
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`type-${field?.id ?? 'new'}`}>Type</Label>
                    <select
                        id={`type-${field?.id ?? 'new'}`}
                        className={select}
                        value={f.type}
                        onChange={(e) => setF({ ...f, type: e.target.value })}
                    >
                        {types.map((t) => (
                            <option key={t.value} value={t.value}>
                                {t.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`unit-${field?.id ?? 'new'}`}>Unit</Label>
                    <Input
                        id={`unit-${field?.id ?? 'new'}`}
                        value={f.unit}
                        onChange={(e) => setF({ ...f, unit: e.target.value })}
                        className="w-28"
                        placeholder="m², ha, %"
                    />
                </div>
                {field && (
                    <span className="pb-2 font-mono text-xs text-muted-foreground">
                        key: {field.key}
                    </span>
                )}
            </div>
            {f.type === 'select' && (
                <div className="grid max-w-md gap-1.5">
                    <Label htmlFor={`options-${field?.id ?? 'new'}`}>
                        Choices (one per line)
                    </Label>
                    <textarea
                        id={`options-${field?.id ?? 'new'}`}
                        value={f.options}
                        onChange={(e) =>
                            setF({ ...f, options: e.target.value })
                        }
                        className="min-h-20 rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                </div>
            )}
            <div className="flex flex-wrap gap-x-5 gap-y-2">
                <Check
                    label="Required"
                    checked={f.required}
                    onChange={(v) => setF({ ...f, required: v })}
                />
                <Check
                    label="Show on cards"
                    checked={f.on_card}
                    onChange={(v) => setF({ ...f, on_card: v })}
                />
                <Check
                    label="Show in key facts"
                    checked={f.key_facts}
                    onChange={(v) => setF({ ...f, key_facts: v })}
                />
                <Check
                    label="Use as a search filter"
                    checked={f.filterable}
                    onChange={(v) => setF({ ...f, filterable: v })}
                />
            </div>
            <InputError
                message={errors.label ?? errors.options ?? errors.type}
            />
            <div className="flex flex-wrap gap-2">
                <Button
                    size="sm"
                    variant={field ? 'outline' : 'default'}
                    onClick={save}
                >
                    {field ? 'Save field' : 'Add field'}
                </Button>
                {field && (
                    <>
                        <Button
                            size="sm"
                            variant="ghost"
                            disabled={isFirst}
                            aria-label={`Move ${field.label} up`}
                            onClick={() =>
                                router.post(
                                    fieldsRoutes.move({
                                        category: slug,
                                        field: field.id,
                                    }).url,
                                    { direction: 'up' },
                                    opts,
                                )
                            }
                        >
                            <ArrowUp />
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            disabled={isLast}
                            aria-label={`Move ${field.label} down`}
                            onClick={() =>
                                router.post(
                                    fieldsRoutes.move({
                                        category: slug,
                                        field: field.id,
                                    }).url,
                                    { direction: 'down' },
                                    opts,
                                )
                            }
                        >
                            <ArrowDown />
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() =>
                                window.confirm(
                                    `Remove the field “${field.label}”?`,
                                ) &&
                                router.delete(
                                    fieldsRoutes.destroy({
                                        category: slug,
                                        field: field.id,
                                    }).url,
                                    opts,
                                )
                            }
                        >
                            Remove
                        </Button>
                    </>
                )}
            </div>
        </div>
    );
}

function DocumentEditor({
    slug,
    doc,
    visibilities,
    formats,
}: {
    slug: string;
    doc?: DocType;
    visibilities: Option[];
    formats: string[];
}) {
    const [d, setD] = useState({
        name: doc?.name ?? '',
        mandatory: doc?.mandatory ?? false,
        visibility: doc?.visibility ?? 'verified',
        formats: doc?.formats ?? ['pdf', 'jpg', 'png'],
        max_size_mb: doc?.maxSizeMb ?? 20,
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const save = () => {
        const done = {
            ...opts,
            onError: setErrors,
            onSuccess: () => {
                setErrors({});
                if (!doc) setD({ ...d, name: '' });
            },
        };
        if (doc)
            router.put(
                documents.update({ category: slug, document: doc.id }).url,
                d,
                done,
            );
        else router.post(documents.store(slug).url, d, done);
    };

    return (
        <div className="grid gap-3 border-b border-border px-5 py-4 last:border-b-0">
            <div className="flex flex-wrap items-end gap-3">
                <div className="grid gap-1.5">
                    <Label htmlFor={`doc-${doc?.id ?? 'new'}`}>
                        {doc ? 'Document' : 'New document type'}
                    </Label>
                    <Input
                        id={`doc-${doc?.id ?? 'new'}`}
                        value={d.name}
                        onChange={(e) => setD({ ...d, name: e.target.value })}
                        className="w-72"
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`vis-${doc?.id ?? 'new'}`}>
                        Who can open it
                    </Label>
                    <select
                        id={`vis-${doc?.id ?? 'new'}`}
                        className={select}
                        value={d.visibility}
                        onChange={(e) =>
                            setD({ ...d, visibility: e.target.value })
                        }
                    >
                        {visibilities.map((v) => (
                            <option key={v.value} value={v.value}>
                                {v.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`size-${doc?.id ?? 'new'}`}>
                        Max size (MB)
                    </Label>
                    <Input
                        id={`size-${doc?.id ?? 'new'}`}
                        type="number"
                        min={1}
                        max={50}
                        value={d.max_size_mb}
                        onChange={(e) =>
                            setD({ ...d, max_size_mb: Number(e.target.value) })
                        }
                        className="w-24"
                    />
                </div>
            </div>
            <div className="flex flex-wrap gap-x-5 gap-y-2">
                <Check
                    label="Mandatory before review"
                    checked={d.mandatory}
                    onChange={(v) => setD({ ...d, mandatory: v })}
                />
                {formats.map((fmt) => (
                    <Check
                        key={fmt}
                        label={fmt.toUpperCase()}
                        checked={d.formats.includes(fmt)}
                        onChange={(v) =>
                            setD({
                                ...d,
                                formats: v
                                    ? [...d.formats, fmt]
                                    : d.formats.filter((x) => x !== fmt),
                            })
                        }
                    />
                ))}
            </div>
            <InputError
                message={errors.name ?? errors.formats ?? errors.visibility}
            />
            <div className="flex gap-2">
                <Button
                    size="sm"
                    variant={doc ? 'outline' : 'default'}
                    onClick={save}
                >
                    {doc ? 'Save' : 'Add document type'}
                </Button>
                {doc && (
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() =>
                            window.confirm(`Remove “${doc.name}”?`) &&
                            router.delete(
                                documents.destroy({
                                    category: slug,
                                    document: doc.id,
                                }).url,
                                opts,
                            )
                        }
                    >
                        Remove
                    </Button>
                )}
            </div>
        </div>
    );
}

export default function CategoryEdit({
    category,
    fields,
    documents: docs,
    options,
}: Props) {
    const [c, setC] = useState({
        name: category.name,
        description: category.description ?? '',
        icon: category.icon ?? '',
        default_deposit: category.defaultDeposit,
        default_fee_percent: category.defaultFeePercent,
        default_deposit_terms: category.defaultDepositTerms,
        requires_compliance_notice: category.requiresComplianceNotice,
        is_visible: category.visible,
        seo_title: category.seoTitle ?? '',
        seo_description: category.seoDescription ?? '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});

    return (
        <>
            <Head title={category.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <h1 className="font-display text-3xl">{category.name}</h1>

                <section
                    className="grid gap-4 rounded-xl border border-border bg-card p-5"
                    aria-labelledby="defaults-h"
                >
                    <h2 id="defaults-h" className="font-semibold">
                        Details and defaults for new listings
                    </h2>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="c-name">Name</Label>
                            <Input
                                id="c-name"
                                value={c.name}
                                onChange={(e) =>
                                    setC({ ...c, name: e.target.value })
                                }
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="c-icon">Icon name</Label>
                            <Input
                                id="c-icon"
                                value={c.icon}
                                onChange={(e) =>
                                    setC({ ...c, icon: e.target.value })
                                }
                                placeholder="house, trees, pickaxe, store"
                            />
                        </div>
                        <div className="grid gap-1.5 md:col-span-2">
                            <Label htmlFor="c-desc">Description</Label>
                            <Input
                                id="c-desc"
                                value={c.description}
                                onChange={(e) =>
                                    setC({ ...c, description: e.target.value })
                                }
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="c-dep">Default deposit (R)</Label>
                            <Input
                                id="c-dep"
                                type="number"
                                min={0}
                                value={c.default_deposit}
                                onChange={(e) =>
                                    setC({
                                        ...c,
                                        default_deposit: Number(e.target.value),
                                    })
                                }
                                className="tabular"
                            />
                            <InputError message={errors.default_deposit} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="c-fee">
                                Default platform fee (%)
                            </Label>
                            <Input
                                id="c-fee"
                                type="number"
                                min={0}
                                max={50}
                                step={0.5}
                                value={c.default_fee_percent}
                                onChange={(e) =>
                                    setC({
                                        ...c,
                                        default_fee_percent: Number(
                                            e.target.value,
                                        ),
                                    })
                                }
                                className="tabular"
                            />
                            <InputError message={errors.default_fee_percent} />
                        </div>
                        <div className="grid gap-1.5 md:col-span-2">
                            <Label htmlFor="c-terms">Deposit terms</Label>
                            <select
                                id="c-terms"
                                className={select}
                                value={c.default_deposit_terms}
                                onChange={(e) =>
                                    setC({
                                        ...c,
                                        default_deposit_terms: e.target.value,
                                    })
                                }
                            >
                                {options.depositTerms.map((t) => (
                                    <option key={t.value} value={t.value}>
                                        {t.label}
                                    </option>
                                ))}
                            </select>
                            <p className="text-sm text-muted-foreground">
                                A security deposit is held separately and
                                refunded in full; it’s never applied to the
                                price or forfeited automatically.
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-x-6 gap-y-2">
                        <Check
                            label="Show the verification and approvals notice on every listing"
                            checked={c.requires_compliance_notice}
                            onChange={(v) =>
                                setC({ ...c, requires_compliance_notice: v })
                            }
                        />
                        <Check
                            label="Show this category on the website"
                            checked={c.is_visible}
                            onChange={(v) => setC({ ...c, is_visible: v })}
                        />
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="c-seot">Search title</Label>
                            <Input
                                id="c-seot"
                                value={c.seo_title}
                                onChange={(e) =>
                                    setC({ ...c, seo_title: e.target.value })
                                }
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="c-seod">Search description</Label>
                            <Input
                                id="c-seod"
                                value={c.seo_description}
                                onChange={(e) =>
                                    setC({
                                        ...c,
                                        seo_description: e.target.value,
                                    })
                                }
                            />
                        </div>
                    </div>
                    <div>
                        <Button
                            onClick={() =>
                                router.put(update(category.slug).url, c, {
                                    ...opts,
                                    onError: setErrors,
                                    onSuccess: () => setErrors({}),
                                })
                            }
                        >
                            Save category
                        </Button>
                    </div>
                </section>

                <section
                    className="rounded-xl border border-border bg-card"
                    aria-labelledby="fields-h"
                >
                    <div className="border-b border-border px-5 py-4">
                        <h2 id="fields-h" className="font-semibold">
                            Key facts
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Fields every listing in this category fills in. A
                            field’s key can’t change once created.
                        </p>
                    </div>
                    {fields.map((field, i) => (
                        <FieldEditor
                            key={field.id}
                            slug={category.slug}
                            field={field}
                            types={options.types}
                            isFirst={i === 0}
                            isLast={i === fields.length - 1}
                        />
                    ))}
                    <FieldEditor slug={category.slug} types={options.types} />
                </section>

                <section
                    className="rounded-xl border border-border bg-card"
                    aria-labelledby="docs-h"
                >
                    <div className="border-b border-border px-5 py-4">
                        <h2 id="docs-h" className="font-semibold">
                            Document types
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Documents are never public. Mandatory ones must be
                            uploaded before a listing can be submitted for
                            review.
                        </p>
                    </div>
                    {docs.map((doc) => (
                        <DocumentEditor
                            key={doc.id}
                            slug={category.slug}
                            doc={doc}
                            visibilities={options.visibilities}
                            formats={options.formats}
                        />
                    ))}
                    <DocumentEditor
                        slug={category.slug}
                        visibilities={options.visibilities}
                        formats={options.formats}
                    />
                </section>
            </div>
        </>
    );
}

CategoryEdit.layout = {
    breadcrumbs: [{ title: 'Categories', href: index() }],
};
