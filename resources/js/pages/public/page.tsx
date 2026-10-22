import { Head } from '@inertiajs/react';
import type { Block } from '@/components/content-blocks';
import ContentBlocks from '@/components/content-blocks';

type Props = {
    page: { title: string; blocks: Block[]; updatedAt: string | null };
    seo: { title: string; description: string };
};

export default function ContentPage({ page, seo }: Props) {
    return (
        <>
            <Head title={seo.title}>
                {seo.description && (
                    <meta name="description" content={seo.description} />
                )}
            </Head>
            <article className="mx-auto max-w-[1280px] px-5 py-12 sm:px-8 sm:py-16">
                <h1 className="font-display text-4xl leading-tight sm:text-5xl">
                    {page.title}
                </h1>
                {page.updatedAt && (
                    <p className="mt-2 text-sm text-muted-foreground">
                        Last updated {page.updatedAt}
                    </p>
                )}
                <div className="mt-8">
                    <ContentBlocks blocks={page.blocks} />
                </div>
            </article>
        </>
    );
}
