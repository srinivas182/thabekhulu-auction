import AuthBrandLayout from '@/layouts/auth/auth-brand-layout';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    title = '',
    description = '',
    variant = 'simple',
    children,
}: {
    title?: string;
    description?: string;
    /** 'brand' gives a page the photo panel and journey rail (used by registration). */
    variant?: 'simple' | 'brand';
    children: React.ReactNode;
}) {
    if (variant === 'brand') {
        return (
            <AuthBrandLayout title={title} description={description}>
                {children}
            </AuthBrandLayout>
        );
    }

    return (
        <AuthLayoutTemplate title={title} description={description}>
            {children}
        </AuthLayoutTemplate>
    );
}
