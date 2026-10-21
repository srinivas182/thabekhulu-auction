import { Head, Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';

type Props = { status: number };

const messages: Record<number, { title: string; body: string }> = {
    403: {
        title: 'You don’t have access to this page',
        body: 'Your account doesn’t have permission for this page. Contact support if you think this is wrong.',
    },
    404: {
        title: 'Page not found',
        body: 'This page doesn’t exist or the listing has been removed. Check the address or browse current auctions.',
    },
    419: {
        title: 'Your session expired',
        body: 'For your security, the page timed out. Go back, refresh the page and try again.',
    },
    429: {
        title: 'Too many requests',
        body: 'Please wait a minute before trying again.',
    },
    500: {
        title: 'Something went wrong on our side',
        body: 'The error has been logged and our team will look into it. Please try again in a few minutes.',
    },
    503: {
        title: 'We’re doing scheduled maintenance',
        body: 'The platform will be back shortly. Live auctions are extended to cover any downtime.',
    },
};

export default function ErrorPage({ status }: Props) {
    const message = messages[status] ?? messages[500];

    return (
        <>
            <Head title={message.title} />
            <main className="flex min-h-svh items-center justify-center bg-background px-6">
                <div className="max-w-lg">
                    <AppLogoIcon className="h-14 w-auto text-primary" />
                    <p className="tabular mt-8 text-sm text-muted-foreground">
                        Error {status}
                    </p>
                    <h1 className="mt-2 font-display text-4xl">
                        {message.title}
                    </h1>
                    <p className="mt-4 text-lg text-muted-foreground">
                        {message.body}
                    </p>
                    <Link
                        href={home()}
                        className="mt-8 inline-block rounded-md bg-primary px-5 py-3 font-semibold text-primary-foreground hover:opacity-90"
                    >
                        Go to the home page
                    </Link>
                </div>
            </main>
        </>
    );
}
