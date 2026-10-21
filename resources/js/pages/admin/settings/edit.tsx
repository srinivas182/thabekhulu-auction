import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { edit, update } from '@/routes/admin/settings';

type Field = {
    name: string;
    type: 'text' | 'email' | 'tel' | 'textarea' | 'number' | 'boolean' | 'list';
    label: string;
    help: string | null;
    prefix: string | null;
    suffix: string | null;
    readOnly: boolean;
};

type Props = {
    group: string;
    groups: { key: string; label: string }[];
    definition: { label: string; description: string; fields: Field[] };
    values: Record<string, unknown>;
    canManage: boolean;
    currentIp: string;
};

const asText = (value: unknown): string =>
    typeof value === 'string' || typeof value === 'number'
        ? value.toString()
        : '';

const textareaClass =
    'min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-60';

export default function SettingsEdit({
    group,
    groups,
    definition,
    values,
    canManage,
    currentIp,
}: Props) {
    const disabled = (field: Field) => field.readOnly || !canManage;

    return (
        <>
            <Head title={`${definition.label} settings`} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">Settings</h1>
                    <p className="mt-1 text-muted-foreground">
                        Every change is recorded in the audit log.
                    </p>
                </div>

                <nav
                    aria-label="Settings sections"
                    className="flex gap-1 border-b border-border"
                >
                    {groups.map((g) => (
                        <Link
                            key={g.key}
                            href={edit(g.key)}
                            preserveScroll
                            className={cn(
                                '-mb-px border-b-2 px-4 py-2 text-sm font-medium',
                                g.key === group
                                    ? 'border-primary text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                            aria-current={g.key === group ? 'page' : undefined}
                        >
                            {g.label}
                        </Link>
                    ))}
                </nav>

                <section className="max-w-2xl">
                    <p className="text-muted-foreground">
                        {definition.description}
                    </p>

                    <Form
                        {...update.form(group)}
                        options={{ preserveScroll: true }}
                        className="mt-6 flex flex-col gap-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                {definition.fields.map((field) => {
                                    const value = values[field.name];
                                    const id = `setting-${field.name}`;

                                    if (field.type === 'boolean') {
                                        return (
                                            <div
                                                key={field.name}
                                                className="grid gap-1"
                                            >
                                                <label
                                                    htmlFor={id}
                                                    className="flex items-center gap-3 font-medium"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name={field.name}
                                                        value="0"
                                                    />
                                                    <input
                                                        id={id}
                                                        type="checkbox"
                                                        name={field.name}
                                                        value="1"
                                                        defaultChecked={Boolean(
                                                            value,
                                                        )}
                                                        disabled={disabled(
                                                            field,
                                                        )}
                                                        className="size-4 accent-primary"
                                                    />
                                                    {field.label}
                                                </label>
                                                {field.help && (
                                                    <p className="pl-7 text-sm text-muted-foreground">
                                                        {field.help}
                                                    </p>
                                                )}
                                                <InputError
                                                    message={errors[field.name]}
                                                />
                                            </div>
                                        );
                                    }

                                    return (
                                        <div
                                            key={field.name}
                                            className="grid gap-2"
                                        >
                                            <Label htmlFor={id}>
                                                {field.label}
                                            </Label>
                                            {field.type === 'textarea' ||
                                            field.type === 'list' ? (
                                                <textarea
                                                    id={id}
                                                    name={field.name}
                                                    className={textareaClass}
                                                    disabled={disabled(field)}
                                                    defaultValue={
                                                        field.type === 'list'
                                                            ? (
                                                                  (value as string[]) ??
                                                                  []
                                                              ).join('\n')
                                                            : asText(value)
                                                    }
                                                />
                                            ) : (
                                                <div className="flex items-center gap-2">
                                                    {field.prefix && (
                                                        <span className="text-muted-foreground">
                                                            {field.prefix}
                                                        </span>
                                                    )}
                                                    <Input
                                                        id={id}
                                                        name={field.name}
                                                        type={field.type}
                                                        inputMode={
                                                            field.type ===
                                                            'number'
                                                                ? 'numeric'
                                                                : undefined
                                                        }
                                                        defaultValue={asText(
                                                            value,
                                                        )}
                                                        disabled={disabled(
                                                            field,
                                                        )}
                                                        className={cn(
                                                            field.type ===
                                                                'number' &&
                                                                'tabular max-w-40',
                                                        )}
                                                    />
                                                    {field.suffix && (
                                                        <span className="text-sm text-muted-foreground">
                                                            {field.suffix}
                                                        </span>
                                                    )}
                                                </div>
                                            )}
                                            {field.help && (
                                                <p className="text-sm text-muted-foreground">
                                                    {field.help}
                                                    {field.name ===
                                                        'admin_ip_allowlist' &&
                                                        ` Your current address is ${currentIp}.`}
                                                </p>
                                            )}
                                            <InputError
                                                message={errors[field.name]}
                                            />
                                        </div>
                                    );
                                })}

                                {canManage && (
                                    <div>
                                        <Button disabled={processing}>
                                            Save changes
                                        </Button>
                                    </div>
                                )}
                            </>
                        )}
                    </Form>
                </section>
            </div>
        </>
    );
}

SettingsEdit.layout = {
    breadcrumbs: [{ title: 'Settings', href: edit() }],
};
