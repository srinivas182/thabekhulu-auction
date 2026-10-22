import { Head, Link } from '@inertiajs/react';

type Props = {
    post: {
        title: string;
        topic: string;
        excerpt: string | null;
        date: string | null;
        image: string | null;
        body: string | null;
    };
    seo: { title: string; description: string };
};

export default function InsightShow({ post, seo }: Props) {
    return (
        <>
            <Head title={seo.title}>
                {seo.description && (
                    <meta name="description" content={seo.description} />
                )}
            </Head>
            <article className="mx-auto max-w-[860px] px-5 py-12 sm:px-8 sm:py-16">
                <p className="text-sm">
                    <Link href="/insights">Investor insights</Link>{' '}
                    <span className="text-muted-foreground">
                        · {post.topic} · {post.date}
                    </span>
                </p>
                <h1 className="mt-3 font-display text-4xl leading-tight sm:text-5xl">
                    {post.title}
                </h1>
                {post.excerpt && (
                    <p className="mt-4 text-xl text-muted-foreground">
                        {post.excerpt}
                    </p>
                )}
                {post.image && (
                    <img
                        src={post.image}
                        alt=""
                        className="mt-8 w-full rounded-[18px]"
                    />
                )}
                {post.body && (
                    <div
                        className="mt-8 text-[17px] leading-relaxed [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:pl-4 [&_h2]:mt-8 [&_h2]:font-display [&_h2]:text-3xl [&_h3]:mt-6 [&_h3]:text-xl [&_h3]:font-semibold [&_li]:mt-1 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:mt-4 [&_ul]:list-disc [&_ul]:pl-6"
                        dangerouslySetInnerHTML={{ __html: post.body }}
                    />
                )}
            </article>
        </>
    );
}
