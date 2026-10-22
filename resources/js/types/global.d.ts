import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            site: {
                header: { label: string; url: string; newTab: boolean }[];
                footer: { label: string; url: string; newTab: boolean }[];
                ffc: string;
            };
            theme: {
                scope: 'website' | 'admin';
                logoLight: string | null;
                logoDark: string | null;
                favicon: string | null;
                faviconApple: string | null;
            };
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
