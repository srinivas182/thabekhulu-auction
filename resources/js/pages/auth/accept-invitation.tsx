import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { home } from '@/routes';
import { accept } from '@/routes/invitations';

type Props = {
    token: string;
    valid: boolean;
    email: string | null;
    role: string | null;
    passwordRules: string;
};

export default function AcceptInvitation({
    token,
    valid,
    email,
    role,
    passwordRules,
}: Props) {
    if (!valid) {
        return (
            <>
                <Head title="Invitation not valid" />
                <div className="space-y-4 text-center">
                    <p className="text-muted-foreground">
                        This invitation has expired, was revoked, or has already
                        been used. Ask your administrator to send a new one.
                    </p>
                    <TextLink href={home()}>Go to the home page</TextLink>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Accept invitation" />
            <p className="mb-6 rounded-md border border-border bg-muted px-4 py-3 text-sm">
                You’re joining as <strong>{role}</strong> with{' '}
                <strong>{email}</strong>.
            </p>
            <Form
                {...accept.form(token)}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <InputError message={errors.token} />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="first_name">First name</Label>
                                <Input
                                    id="first_name"
                                    name="first_name"
                                    required
                                    autoFocus
                                    autoComplete="given-name"
                                />
                                <InputError message={errors.first_name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="last_name">Last name</Label>
                                <Input
                                    id="last_name"
                                    name="last_name"
                                    required
                                    autoComplete="family-name"
                                />
                                <InputError message={errors.last_name} />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="mobile">Mobile number</Label>
                            <Input
                                id="mobile"
                                name="mobile"
                                type="tel"
                                required
                                autoComplete="tel"
                                placeholder="082 123 4567"
                            />
                            <InputError message={errors.mobile} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password">Password</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                            />
                            <InputError message={errors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirm password
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>
                        <Button type="submit" className="w-full">
                            {processing && <Spinner />}
                            Create my account
                        </Button>
                        <p className="text-center text-sm text-muted-foreground">
                            Next, you’ll set up two-factor authentication.
                        </p>
                    </div>
                )}
            </Form>
        </>
    );
}

AcceptInvitation.layout = {
    title: 'Join the Thabekhulu back office',
    description: 'Set up your staff account.',
};
