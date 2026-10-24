import { router, usePage } from '@inertiajs/react';
import { Heart, Link as LinkIcon, Mail, MessageCircle } from 'lucide-react';
import { useState } from 'react';
import { watch } from '@/routes/auctions';

/** Share links carry campaign tags (utm_*) so registrations can be traced to them; buyers can watch a listing. */
export default function ShareWatch({
    ulid,
    title,
    url,
    watching,
}: {
    ulid: string;
    title: string;
    url: string;
    watching: boolean;
}) {
    const user =
        (usePage().props.auth?.user as { type?: string } | null | undefined) ??
        null;
    const [copied, setCopied] = useState(false);
    const tagged = (medium: string) =>
        `${url}${url.includes('?') ? '&' : '?'}utm_source=share&utm_medium=${medium}&utm_campaign=listing`;
    const text = `${title} on Thabekhulu auctions`;
    const btn =
        'inline-flex min-h-10 items-center gap-1.5 rounded-[10px] border border-input px-3 text-sm font-semibold';

    return (
        <div className="flex flex-wrap gap-2">
            {user?.type === 'buyer' && (
                <button
                    type="button"
                    aria-pressed={watching}
                    onClick={() =>
                        router.post(
                            watch(ulid).url,
                            {},
                            { preserveScroll: true },
                        )
                    }
                    className={btn}
                >
                    <Heart
                        className={
                            watching
                                ? 'size-4 fill-current text-brand-accent'
                                : 'size-4'
                        }
                        aria-hidden="true"
                    />
                    {watching ? 'Watching' : 'Watch'}
                </button>
            )}
            <a
                className={btn}
                href={`https://wa.me/?text=${encodeURIComponent(`${text} ${tagged('whatsapp')}`)}`}
                target="_blank"
                rel="noreferrer"
            >
                <MessageCircle className="size-4" aria-hidden="true" />
                WhatsApp
            </a>
            <a
                className={btn}
                href={`mailto:?subject=${encodeURIComponent(text)}&body=${encodeURIComponent(tagged('email'))}`}
            >
                <Mail className="size-4" aria-hidden="true" />
                Email
            </a>
            <button
                type="button"
                className={btn}
                onClick={() => {
                    void navigator.clipboard?.writeText(tagged('copy'));
                    setCopied(true);
                }}
            >
                <LinkIcon className="size-4" aria-hidden="true" />
                {copied ? 'Link copied' : 'Copy link'}
            </button>
        </div>
    );
}
