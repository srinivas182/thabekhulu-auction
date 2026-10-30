import { cn } from '@/lib/utils';

/** "Developed and maintained by Mayura Consultancy Services", centred at the foot of the site and dashboards. */
export default function SiteCredit({
    className,
    tone = 'muted',
}: {
    className?: string;
    tone?: 'muted' | 'onDark';
}) {
    return (
        <p
            className={cn(
                'px-4 py-4 text-center text-xs',
                tone === 'onDark'
                    ? 'text-[#cfe3da]/80'
                    : 'text-muted-foreground',
                className,
            )}
        >
            Developed and maintained by{' '}
            <a
                href="https://www.mayuraconsultancy.com"
                target="_blank"
                rel="noopener noreferrer"
                className="font-medium underline underline-offset-2 hover:no-underline"
            >
                Mayura Consultancy Services
            </a>
        </p>
    );
}
