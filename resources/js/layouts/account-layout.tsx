import { AccountSidebar } from '@/components/account-sidebar';
import { AppContent } from '@/components/app-content';
import SiteCredit from '@/components/site-credit';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import type { AppLayoutProps } from '@/types';

/** Buyer (and seller) account area: a persistent left menu, a slide-out menu on phones. */
export default function AccountLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AccountSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
                <SiteCredit className="mt-auto" />
            </AppContent>
        </AppShell>
    );
}
