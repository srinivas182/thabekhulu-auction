import { Head, Link } from '@inertiajs/react';
import { register } from '@/routes';

const categories = [
    {
        name: 'Homes',
        detail: 'Houses, townhouses and apartments, many renovated by Thabekhulu before sale.',
    },
    {
        name: 'Land',
        detail: 'Farms, plots and development land, with zoning and title documents ready to review.',
    },
    {
        name: 'Mining',
        detail: 'Mining and prospecting properties, sold subject to ministerial consent where required.',
    },
    {
        name: 'Malls',
        detail: 'Shopping centres and retail property, with rent rolls and lease schedules for bidders.',
    },
];

/**
 * Pre-launch home page. Sprint 1 delivers the approved home page design;
 * this placeholder confirms branding, server-side rendering and the shell.
 */
export default function Home() {
    return (
        <>
            <Head title="Property auctions in South Africa">
                <meta
                    name="description"
                    content="Bid online for homes, land, mining and commercial property across South Africa with Thabekhulu Development Group."
                />
            </Head>

            <section className="bg-brand text-brand-foreground">
                <div className="mx-auto max-w-6xl px-5 py-20 sm:px-8 sm:py-28">
                    <h1 className="max-w-3xl font-display text-5xl leading-[1.05] sm:text-7xl">
                        Bid on South African property, from anywhere.
                    </h1>
                    <p className="mt-6 max-w-2xl text-lg opacity-85 sm:text-xl">
                        Homes, land, mining and commercial property sold by open
                        online auction. Every bid is visible, every document is
                        ready before you bid.
                    </p>
                    <Link
                        href={register()}
                        className="mt-10 inline-block rounded-md bg-brand-foreground px-6 py-3 font-semibold text-brand hover:opacity-90 focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-brand-accent"
                    >
                        Register to bid
                    </Link>
                </div>
            </section>

            <section className="mx-auto max-w-6xl px-5 py-16 sm:px-8">
                <h2 className="font-display text-3xl sm:text-4xl">
                    Auctions open soon in four categories
                </h2>
                <div className="mt-8 grid gap-px overflow-hidden rounded-xl border border-border bg-border sm:grid-cols-2">
                    {categories.map((category) => (
                        <article
                            key={category.name}
                            className="bg-card p-6 sm:p-8"
                        >
                            <h3 className="font-display text-2xl">
                                {category.name}
                            </h3>
                            <p className="mt-2 max-w-prose text-muted-foreground">
                                {category.detail}
                            </p>
                        </article>
                    ))}
                </div>
            </section>
        </>
    );
}
