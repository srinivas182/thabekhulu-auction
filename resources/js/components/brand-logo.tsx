import { usePage } from '@inertiajs/react';
import BrandLockup from '@/components/brand-lockup';
import { cn } from '@/lib/utils';

/**
 * The published theme's logo, with a dark-mode variant when one is set.
 * Falls back to the built-in Thabekhulu lockup.
 */
export default function BrandLogo({ className }: { className?: string }) {
    const { theme } = usePage().props;

    if (!theme.logoLight) {
        return <BrandLockup className={className} />;
    }

    return (
        <>
            <img
                src={theme.logoLight}
                alt="Thabekhulu Development Group"
                className={cn(className, theme.logoDark && 'dark:hidden')}
            />
            {theme.logoDark && (
                <img
                    src={theme.logoDark}
                    alt="Thabekhulu Development Group"
                    className={cn(className, 'hidden dark:block')}
                />
            )}
        </>
    );
}
