import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    return (
        <>
            <Head title="Register to bid" />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="first_name">
                                        First name
                                    </Label>
                                    <Input
                                        className="h-11"
                                        id="first_name"
                                        type="text"
                                        required
                                        autoFocus
                                        autoComplete="given-name"
                                        name="first_name"
                                    />
                                    <InputError message={errors.first_name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="last_name">Last name</Label>
                                    <Input
                                        className="h-11"
                                        id="last_name"
                                        type="text"
                                        required
                                        autoComplete="family-name"
                                        name="last_name"
                                    />
                                    <InputError message={errors.last_name} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    className="h-11"
                                    id="email"
                                    type="email"
                                    required
                                    autoComplete="email"
                                    name="email"
                                    placeholder="you@example.com"
                                    aria-describedby="email-hint"
                                />
                                <p
                                    id="email-hint"
                                    className="text-sm text-muted-foreground"
                                >
                                    We’ll send a link to confirm it.
                                </p>
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="mobile">Mobile number</Label>
                                <Input
                                    className="h-11"
                                    id="mobile"
                                    type="tel"
                                    required
                                    autoComplete="tel"
                                    name="mobile"
                                    placeholder="082 123 4567"
                                    aria-describedby="mobile-hint"
                                />
                                <p
                                    id="mobile-hint"
                                    className="text-sm text-muted-foreground"
                                >
                                    For bid confirmation codes and auction
                                    alerts.
                                </p>
                                <InputError message={errors.mobile} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <PasswordInput
                                    className="h-11"
                                    id="password"
                                    required
                                    autoComplete="new-password"
                                    name="password"
                                    passwordrules={passwordRules}
                                />
                                <p className="text-sm text-muted-foreground">
                                    At least 12 characters, with letters and
                                    numbers.
                                </p>
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>
                                <PasswordInput
                                    className="h-11"
                                    id="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="terms"
                                        name="terms"
                                        value="1"
                                        className="mt-0.5"
                                    />
                                    <Label
                                        htmlFor="terms"
                                        className="leading-snug font-normal"
                                    >
                                        I accept the Terms of Use and consent to
                                        Thabekhulu processing my personal
                                        information as described in the{' '}
                                        <a
                                            href="/privacy"
                                            target="_blank"
                                            rel="noreferrer"
                                            className="underline underline-offset-2"
                                        >
                                            Privacy Notice
                                        </a>{' '}
                                        (POPIA).
                                    </Label>
                                </div>
                                <InputError message={errors.terms} />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 h-12 w-full text-base"
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Create account
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            Already have an account?{' '}
                            <TextLink href={login()}>Log in</TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    variant: 'brand',
    title: 'Create your account',
    description:
        'It takes a minute. You’ll verify your identity before your first bid.',
};
