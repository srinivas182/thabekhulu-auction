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
