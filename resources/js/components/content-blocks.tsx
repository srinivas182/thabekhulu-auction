import { Link } from '@inertiajs/react';

export type Block =
    | { type: 'rich_text'; html: string }
    | {
          type: 'image';
          media_id: number;
          alt: string;
          caption: string;
          url?: string | null;
      }
    | { type: 'two_column'; left: string; right: string }
    | {
          type: 'cta';
          title: string;
          text: string;
          button_label: string;
          button_url: string;
      }
    | { type: 'faq'; items: { question: string; answer: string }[] };

const prose =
    'max-w-[720px] text-[17px] leading-relaxed [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:pl-4 [&_h2]:mt-8 [&_h2]:font-display [&_h2]:text-3xl [&_h3]:mt-6 [&_h3]:text-xl [&_h3]:font-semibold [&_li]:mt-1 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:mt-4 [&_ul]:list-disc [&_ul]:pl-6';

/** Renders page blocks. HTML is cleaned server-side before it is stored. */
export default function ContentBlocks({ blocks }: { blocks: Block[] }) {
    return (
        <div className="flex flex-col gap-12">
            {blocks.map((b, i) => {
                switch (b.type) {
                    case 'rich_text':
                        return (
                            <div
                                key={i}
                                className={prose}
                                dangerouslySetInnerHTML={{ __html: b.html }}
                            />
                        );
                    case 'image':
                        return b.url ? (
                            <figure key={i} className="max-w-[960px]">
                                <img
                                    src={b.url}
                                    alt={b.alt}
                                    loading="lazy"
                                    className="w-full rounded-[18px]"
                                />
                                {b.caption && (
                                    <figcaption className="mt-2 text-sm text-muted-foreground">
                                        {b.caption}
                                    </figcaption>
                                )}
                            </figure>
                        ) : null;
                    case 'two_column':
                        return (
                            <div key={i} className="grid gap-8 md:grid-cols-2">
                                <div
                                    className={prose}
                                    dangerouslySetInnerHTML={{ __html: b.left }}
                                />
                                <div
                                    className={prose}
                                    dangerouslySetInnerHTML={{
                                        __html: b.right,
                                    }}
                                />
                            </div>
                        );
                    case 'cta':
                        return (
                            <section
                                key={i}
                                className="flex flex-wrap items-center justify-between gap-6 rounded-[18px] bg-brand p-8 text-brand-foreground sm:p-12"
                            >
                                <div className="max-w-xl">
                                    <h2 className="font-display text-3xl">
                                        {b.title}
                                    </h2>
                                    {b.text && (
                                        <p className="mt-2 opacity-85">
                                            {b.text}
                                        </p>
                                    )}
                                </div>
                                {b.button_label && b.button_url && (
                                    <Link
                                        href={b.button_url}
                                        className="inline-flex min-h-12 items-center rounded-[10px] bg-brand-foreground px-6 font-semibold text-brand"
                                    >
                                        {b.button_label}
                                    </Link>
                                )}
                            </section>
                        );
                    case 'faq':
                        return (
                            <section
                                key={i}
                                aria-label="Questions and answers"
                                className="max-w-[720px]"
                            >
                                <dl className="divide-y divide-border rounded-[14px] border border-border bg-card">
                                    {b.items.map((item, k) => (
                                        <div key={k} className="px-5 py-4">
                                            <dt className="font-semibold">
                                                {item.question}
                                            </dt>
                                            <dd
                                                className="mt-1.5 text-muted-foreground [&_a]:underline"
                                                dangerouslySetInnerHTML={{
                                                    __html: item.answer,
                                                }}
                                            />
                                        </div>
                                    ))}
                                </dl>
                            </section>
                        );
                    default:
                        return null;
                }
            })}
        </div>
    );
}
