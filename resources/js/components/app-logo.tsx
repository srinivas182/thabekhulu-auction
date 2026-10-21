import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { theme } = usePage().props;

    if (theme.logoLight) {
        return (
            <img
                src={theme.logoLight}
                alt="Thabekhulu Development Group"
                className="h-8 w-auto max-w-40 object-contain"
            />
        );
    }

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon className="size-6" />
            </div>
            <div className="ml-1 grid flex-1 text-left">
                <span className="truncate font-display text-base leading-tight">
                    Thabekhulu
                </span>
                <span className="truncate text-xs leading-tight text-muted-foreground">
                    Back office
                </span>
            </div>
        </>
    );
}
