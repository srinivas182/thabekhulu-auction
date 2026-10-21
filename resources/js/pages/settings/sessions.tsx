import { Form, Head } from '@inertiajs/react';
import { Monitor } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { destroy, index } from '@/routes/sessions';

type Session = {
    device: string;
    ip: string | null;
    lastActive: string;
    current: boolean;
};

export default function Sessions({ sessions }: { sessions: Session[] }) {
    const others = sessions.filter((session) => !session.current).length;

    return (
        <>
            <Head title="Signed-in devices" />
            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Signed-in devices"
                    description="Devices where your account is currently signed in."
                />

                <ul className="divide-y divide-border rounded-lg border border-border">
                    {sessions.map((session, i) => (
                        <li
                            key={i}
                            className="flex items-center gap-3 px-4 py-3"
                        >
                            <Monitor
                                className="size-5 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div className="flex-1">
                                <p className="text-sm font-medium">
                                    {session.device}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {session.ip ?? 'Unknown IP'} ·{' '}
                                    {session.current ? (
                                        <span className="font-medium text-status-live">
                                            This device
                                        </span>
                                    ) : (
                                        `Last active ${session.lastActive}`
                                    )}
                                </p>
                            </div>
                        </li>
                    ))}
                    {sessions.length === 0 && (
                        <li className="px-4 py-3 text-sm text-muted-foreground">
                            Device list isn’t available on this server.
                        </li>
                    )}
                </ul>

                {others > 0 && (
                    <Form
                        {...destroy.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="password">
                                        Confirm your password to sign out{' '}
                                        {others} other{' '}
                                        {others === 1 ? 'device' : 'devices'}
                                    </Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        autoComplete="current-password"
                                    />
                                    <InputError message={errors.password} />
                                </div>
                                <Button disabled={processing} variant="outline">
                                    Sign out other devices
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

Sessions.layout = {
    breadcrumbs: [{ title: 'Signed-in devices', href: index() }],
};
