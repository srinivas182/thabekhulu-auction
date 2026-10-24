import { Head } from '@inertiajs/react';

export type Seo = {
    title: string;
    description: string;
    image: string | null;
    canonical: string;
    type: string;
};

/** Search and link-preview tags, rendered on the server too (SSR), so shared links show a title, text and photo. */
export default function SeoHead({ seo }: { seo: Seo }) {
    return (
        <Head title={seo.title}>
            <meta name="description" content={seo.description} />
            <link rel="canonical" href={seo.canonical} />
            <meta
                property="og:site_name"
                content="Thabekhulu Development Group"
            />
            <meta property="og:locale" content="en_ZA" />
            <meta property="og:type" content={seo.type} />
            <meta property="og:title" content={seo.title} />
            <meta property="og:description" content={seo.description} />
            <meta property="og:url" content={seo.canonical} />
            {seo.image && <meta property="og:image" content={seo.image} />}
            <meta
                name="twitter:card"
                content={seo.image ? 'summary_large_image' : 'summary'}
            />
        </Head>
    );
}
