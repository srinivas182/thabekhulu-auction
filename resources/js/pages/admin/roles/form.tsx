import { Form, Head, router } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, index, store, update } from '@/routes/admin/roles';

type Group = {
    module: string;
    label: string;
    permissions: { name: string; description: string }[];
};

type Role = {
    id: number;
    label: string;
    description: string | null;
    system: boolean;
    permissions: string[];
};

type Props = { role: Role | null; groups: Group[] };

export default function RoleForm({ role, groups }: Props) {
    const readOnly = role?.system ?? false;
    const granted = new Set(role?.permissions ?? []);
    const formRoute = role ? update.form(role.id) : store.form();

    return (
        <>
            <Head title={role ? role.label : 'Create role'} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">
                        {role ? role.label : 'Create role'}
                    </h1>
                    {readOnly && (
                        <p className="mt-1 text-muted-foreground">
                            This is a system role. Its permissions are fixed;
                            create a custom role for a different mix.
                        </p>
                    )}
                </div>

                <Form {...formRoute} className="flex flex-col gap-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid max-w-xl gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="label">Role name</Label>
                                    <Input
                                        id="label"
                                        name="label"
                                        required
                                        defaultValue={role?.label}
                                        disabled={readOnly}
                                    />
                                    <InputError message={errors.label} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="description">
                                        Description
                                    </Label>
                                    <Input
                                        id="description"
                                        name="description"
                                        defaultValue={role?.description ?? ''}
                                        disabled={readOnly}
                                    />
                                    <InputError message={errors.description} />
                                </div>
                            </div>

                            <fieldset className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                <legend className="mb-2 font-semibold">
                                    Permissions
                                </legend>
                                {groups.map((group) => (
                                    <div
                                        key={group.module}
                                        className="rounded-lg border border-border bg-card p-4"
                                    >
                                        <p className="font-medium">
                                            {group.label}
                                        </p>
                                        <div className="mt-2 grid gap-2">
                                            {group.permissions.map(
                                                (permission) => (
                                                    <label
                                                        key={permission.name}
                                                        className="flex items-start gap-2 text-sm"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            name="permissions[]"
                                                            value={
                                                                permission.name
                                                            }
                                                            defaultChecked={granted.has(
                                                                permission.name,
                                                            )}
                                                            disabled={readOnly}
                                                            className="mt-0.5 size-4 accent-primary"
                                                        />
                                                        {permission.description}
                                                    </label>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </fieldset>
                            <InputError message={errors.permissions} />

                            {!readOnly && (
                                <div className="flex flex-wrap gap-3">
                                    <Button disabled={processing}>
                                        {role ? 'Save role' : 'Create role'}
                                    </Button>
                                    {role && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => {
                                                if (
                                                    window.confirm(
                                                        `Delete the ${role.label} role?`,
                                                    )
                                                ) {
                                                    router.delete(
                                                        destroy(role.id).url,
                                                    );
                                                }
                                            }}
                                        >
                                            Delete role
                                        </Button>
                                    )}
                                </div>
                            )}
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

RoleForm.layout = {
    breadcrumbs: [{ title: 'Roles', href: index() }],
};
