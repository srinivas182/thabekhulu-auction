import { Form, Head, router, usePage } from '@inertiajs/react';
import { ShieldAlert, ShieldCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { formatDateTime } from '@/lib/format';
import staffRoutes, { index } from '@/routes/admin/staff';
import { revoke } from '@/routes/admin/staff/invitations';

type StaffMember = {
    ulid: string;
    name: string;
    email: string;
    role: string | null;
    roleLabel: string | null;
    twoFactor: boolean;
    active: boolean;
    lastLoginAt: string | null;
};

type Invitation = {
    id: number;
    email: string;
    roleLabel: string;
    invitedBy: string;
    expiresAt: string;
};

type RoleOption = { name: string; label: string };

type Props = {
    staff: StaffMember[];
    invitations: Invitation[];
    roles: RoleOption[];
};

const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50';

export default function StaffIndex({ staff, invitations, roles }: Props) {
    const can = useCan();
    const { auth } = usePage().props;

    const changeRole = (member: StaffMember, role: string) => {
        router.put(
            staffRoutes.role(member.ulid).url,
            { role },
            { preserveScroll: true },
        );
    };

    const toggleActive = (member: StaffMember) => {
        const action = member.active ? 'deactivate' : 'reactivate';
        const message = member.active
            ? `Deactivate ${member.name}? They will be signed out of every device immediately.`
            : `Reactivate ${member.name}?`;

        if (window.confirm(message)) {
            router.post(
                staffRoutes[action](member.ulid).url,
                {},
                { preserveScroll: true },
            );
        }
    };

    return (
        <>
            <Head title="Staff" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">Staff</h1>
                    <p className="mt-1 text-muted-foreground">
                        Back-office accounts. Staff are deactivated, never
                        deleted, so the audit trail stays complete.
                    </p>
                </div>

                {can('staff.invite') && (
                    <section
                        aria-labelledby="invite-heading"
                        className="rounded-xl border border-border bg-card p-5"
                    >
                        <h2 id="invite-heading" className="font-semibold">
                            Invite a staff member
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            They’ll get an email link, valid for 72 hours, to
                            set a password and two-factor authentication.
                        </p>
                        <Form
                            action={staffRoutes.invite().url}
                            method="post"
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid flex-1 gap-2">
                                        <Label htmlFor="invite-email">
                                            Email address
                                        </Label>
                                        <Input
                                            id="invite-email"
                                            name="email"
                                            type="email"
                                            required
                                        />
                                        <InputError message={errors.email} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="invite-role">
                                            Role
                                        </Label>
                                        <select
                                            id="invite-role"
                                            name="role"
                                            required
                                            className={selectClass}
                                            defaultValue=""
                                        >
                                            <option value="" disabled>
                                                Choose a role
                                            </option>
                                            {roles.map((role) => (
                                                <option
                                                    key={role.name}
                                                    value={role.name}
                                                >
                                                    {role.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.role} />
                                    </div>
                                    <Button disabled={processing}>
                                        Send invitation
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>
                )}

                {invitations.length > 0 && (
                    <section className="rounded-xl border border-border bg-card">
                        <h2 className="border-b border-border px-5 py-4 font-semibold">
                            Pending invitations
                        </h2>
                        <ul className="divide-y divide-border">
                            {invitations.map((invitation) => (
                                <li
                                    key={invitation.id}
                                    className="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {invitation.email}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {invitation.roleLabel} · invited by{' '}
                                            {invitation.invitedBy} · expires{' '}
                                            {formatDateTime(
                                                invitation.expiresAt,
                                            )}
                                        </p>
                                    </div>
                                    {can('staff.invite') && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.delete(
                                                    revoke(invitation.id).url,
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Revoke
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <section className="overflow-x-auto rounded-xl border border-border bg-card">
                    <table className="w-full min-w-[720px] text-left text-sm">
                        <thead className="border-b border-border text-muted-foreground">
                            <tr>
                                <th className="px-5 py-3 font-medium">Name</th>
                                <th className="px-5 py-3 font-medium">Role</th>
                                <th className="px-5 py-3 font-medium">
                                    Two-factor
                                </th>
                                <th className="px-5 py-3 font-medium">
                                    Last sign-in
                                </th>
                                <th className="px-5 py-3 font-medium">
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {staff.map((member) => {
                                const isSelf = member.ulid === auth.user.ulid;

                                return (
                                    <tr
                                        key={member.ulid}
                                        className={
                                            member.active ? '' : 'opacity-60'
                                        }
                                    >
                                        <td className="px-5 py-3">
                                            <p className="font-medium">
                                                {member.name}
                                                {isSelf && (
                                                    <span className="ml-2 text-xs text-muted-foreground">
                                                        (you)
                                                    </span>
                                                )}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {member.email}
                                            </p>
                                        </td>
                                        <td className="px-5 py-3">
                                            {can('staff.invite') && !isSelf ? (
                                                <select
                                                    aria-label={`Role for ${member.name}`}
                                                    className={selectClass}
                                                    value={member.role ?? ''}
                                                    disabled={!member.active}
                                                    onChange={(event) =>
                                                        changeRole(
                                                            member,
                                                            event.target.value,
                                                        )
                                                    }
                                                >
                                                    {roles.map((role) => (
                                                        <option
                                                            key={role.name}
                                                            value={role.name}
                                                        >
                                                            {role.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            ) : (
                                                member.roleLabel
                                            )}
                                        </td>
                                        <td className="px-5 py-3">
                                            {member.twoFactor ? (
                                                <span className="inline-flex items-center gap-1.5 text-status-live">
                                                    <ShieldCheck
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    On
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center gap-1.5 text-status-closing">
                                                    <ShieldAlert
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Not set up
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-5 py-3 text-muted-foreground">
                                            {member.active
                                                ? formatDateTime(
                                                      member.lastLoginAt,
                                                  )
                                                : 'Deactivated'}
                                        </td>
                                        <td className="px-5 py-3 text-right">
                                            {can('staff.deactivate') &&
                                                !isSelf && (
                                                    <Button
                                                        variant={
                                                            member.active
                                                                ? 'outline'
                                                                : 'secondary'
                                                        }
                                                        size="sm"
                                                        onClick={() =>
                                                            toggleActive(member)
                                                        }
                                                    >
                                                        {member.active
                                                            ? 'Deactivate'
                                                            : 'Reactivate'}
                                                    </Button>
                                                )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </section>
            </div>
        </>
    );
}

StaffIndex.layout = {
    breadcrumbs: [{ title: 'Staff', href: index() }],
};
