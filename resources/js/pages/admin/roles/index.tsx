import { Head, Link } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { create, edit, index } from '@/routes/admin/roles';

type Role = {
    id: number;
    label: string;
    description: string | null;
    system: boolean;
    users: number;
    permissions: number;
};

export default function RolesIndex({ roles }: { roles: Role[] }) {
    const can = useCan();

    return (
        <>
            <Head title="Roles" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="font-display text-3xl">Roles</h1>
                        <p className="mt-1 max-w-2xl text-muted-foreground">
                            System roles come with the platform and can’t be
                            changed. Create a custom role when a team needs a
                            different mix of permissions.
                        </p>
                    </div>
                    {can('roles.manage') && (
                        <Button asChild>
                            <Link href={create()}>Create role</Link>
                        </Button>
                    )}
                </div>

                <ul className="divide-y divide-border rounded-xl border border-border bg-card">
                    {roles.map((role) => (
                        <li
                            key={role.id}
                            className="flex flex-wrap items-center justify-between gap-3 px-5 py-4"
                        >
                            <div className="max-w-2xl">
                                <p className="flex items-center gap-2 font-medium">
                                    {role.label}
                                    {role.system && (
                                        <span className="inline-flex items-center gap-1 text-xs font-normal text-muted-foreground">
                                            <Lock
                                                className="size-3"
                                                aria-hidden="true"
                                            />
                                            System
                                        </span>
                                    )}
                                </p>
                                {role.description && (
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        {role.description}
                                    </p>
                                )}
                                <p className="tabular mt-1 text-sm text-muted-foreground">
                                    {role.users}{' '}
                                    {role.users === 1 ? 'person' : 'people'} ·{' '}
                                    {role.permissions} permissions
                                </p>
                            </div>
                            {can('roles.manage') && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={edit(role.id)}>
                                        {role.system ? 'View' : 'Edit'}
                                    </Link>
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Roles', href: index() }],
};
