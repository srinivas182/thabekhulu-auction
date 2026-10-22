import { Head } from '@inertiajs/react';

type Album = {
    title: string;
    description: string | null;
    images: {
        thumb: string | null;
        large: string | null;
        alt: string;
        caption: string;
    }[];
};

export default function GalleryPage({ albums }: { albums: Album[] }) {
    return (
        <>
            <Head title="Gallery | Thabekhulu" />
            <section className="mx-auto max-w-[1280px] px-5 py-12 sm:px-8 sm:py-16">
                <h1 className="font-display text-4xl sm:text-5xl">Gallery</h1>
                {albums.length === 0 && (
                    <p className="mt-6 text-muted-foreground">
                        Photos coming soon.
                    </p>
                )}
                {albums.map((a) => (
                    <section
                        key={a.title}
                        aria-labelledby={`album-${a.title}`}
                        className="mt-12"
                    >
                        <h2
                            id={`album-${a.title}`}
                            className="font-display text-3xl"
                        >
                            {a.title}
                        </h2>
                        {a.description && (
                            <p className="mt-1 text-muted-foreground">
                                {a.description}
                            </p>
                        )}
                        <ul className="mt-5 grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-3">
                            {a.images.map((img, i) => (
                                <li key={i}>
                                    <a
                                        href={img.large ?? '#'}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="block overflow-hidden rounded-[14px]"
                                    >
                                        {img.thumb && (
                                            <img
                                                src={img.thumb}
                                                alt={img.alt}
                                                loading="lazy"
                                                className="aspect-[4/3] w-full object-cover"
                                            />
                                        )}
                                    </a>
                                    {img.caption && (
                                        <p className="mt-1.5 text-sm text-muted-foreground">
                                            {img.caption}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </section>
        </>
    );
}
