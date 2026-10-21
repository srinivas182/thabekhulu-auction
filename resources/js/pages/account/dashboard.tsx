import { Head, Link, usePage } from '@inertiajs/react';
import { edit } from '@/routes/profile';

/**
 * Buyer area placeholder. Sprint 4 builds the buyer dashboard
 * (auctions, deposits, invoices, verification status).
 */
export default function AccountDashboard() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Your account" />
            <section className="mx-auto max-w-3xl px-5 py-16 sm:px-8">
                <h1 className="font-display text-4xl">
                    Welcome, {auth.user.first_name}
                </h1>
                <p className="mt-4 text-lg text-muted-foreground">
                    Your account is ready. When auctions open you’ll verify your
                    identity here, pay listing deposits and follow your bids.
                </p>
                <Link
                    href={edit()}
                    className="mt-8 inline-block rounded-md border border-border bg-card px-5 py-3 font-medium hover:bg-accent"
                >
                    Manage your profile and security
                </Link>
            </section>
        </>
    );
}
