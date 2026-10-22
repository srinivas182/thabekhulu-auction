import { Head, Link } from '@inertiajs/react';

type Post = {
    title: string;
    slug: string;
    topic: string;
    excerpt: string | null;
    date: string | null;
    image: string | null;
};

export default function Insights({
    posts,
}: {
    posts: {
        data: Post[];
        links: { url: string | null; label: string; active: boolean }[];
    };
}) {
    return (
        <>
            <Head title="Investor insights | Thabekhulu">
                <meta
                    name="description"
                    content="Market notes, mining insights, auction results and guidance for buying property at auction in South Africa."
                />
            </Head>
            <section className="mx-auto max-w-[1280px] px-5 py-12 sm:px-8 sm:py-16">
                <h1 className="font-display text-4xl sm:text-5xl">
                    Investor insights
                </h1>
                <p className="mt-3 max-w-2xl text-lg text-muted-foreground">
                    Market notes, mining insights and practical guidance for
                    buying property at auction in South Africa.
                </p>
                {posts.data.length === 0 ? (
                    <p className="mt-10 text-muted-foreground">
                        The first articles are on their way.
                    </p>
                ) : (
                    <ul className="mt-10 grid grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-6">
                        {posts.data.map((p) => (
                            <li
                                key={p.slug}
                                className="relative overflow-hidden rounded-[18px] border border-border bg-card focus-within:ring-2 focus-within:ring-ring"
                            >
                                <div className="aspect-[3/2] bg-muted">
                                    {p.image && (
                                        <img
                                            src={p.image}
                                            alt=""
                                            loading="lazy"
                                            className="size-full object-cover"
                                        />
                                    )}
                                </div>
                                <div className="p-5">
                                    <p className="text-sm text-muted-foreground">
                                        {p.topic} · {p.date}
                                    </p>
                                    <h2 className="mt-1 font-display text-2xl leading-tight">
                                        <Link
                                            href={`/insights/${p.slug}`}
                                            className="after:absolute after:inset-0 focus-visible:outline-none"
                                        >
                                            {p.title}
                                        </Link>
                                    </h2>
                                    {p.excerpt && (
                                        <p className="mt-2 text-muted-foreground">
                                            {p.excerpt}
                                        </p>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </>
    );
}
