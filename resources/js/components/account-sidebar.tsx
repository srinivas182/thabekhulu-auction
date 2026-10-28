import { Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Bell,
    Building2,
    FileText,
    Gavel,
    Heart,
    House,
    Landmark,
    LayoutGrid,
    Search,
    ShieldCheck,
    UserCog,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';

type Item = { title: string; href: string; icon: LucideIcon; exact?: boolean };

/** Buyer account menu, in the order a buyer works through it. */
const groups: { label: string | null; items: Item[] }[] = [
    {
        label: null,
        items: [
            {
                title: 'Dashboard',
                href: '/account',
                icon: LayoutGrid,
                exact: true,
            },
        ],
    },
    {
        label: 'Get ready to bid',
        items: [
            {
                title: 'Verification',
                href: '/account/verification',
                icon: ShieldCheck,
            },
            { title: 'Bank account', href: '/account/bank', icon: Landmark },
            {
                title: 'Companies and trusts',
                href: '/account/entities',
                icon: Building2,
            },
        ],
    },
    {
        label: 'Auctions',
        items: [
            {
                title: 'My auctions',
                href: '/account/approvals',
                icon: BadgeCheck,
            },
            { title: 'Purchases', href: '/account/purchases', icon: Gavel },
            {
                title: 'Saved and watching',
                href: '/account/saved',
                icon: Heart,
            },
            { title: 'Alerts', href: '/account/notifications', icon: Bell },
        ],
    },
    {
        label: 'More',
        items: [
            { title: 'Quotations', href: '/account/quotes', icon: FileText },
            {
                title: 'Profile and security',
                href: '/settings/profile',
                icon: UserCog,
            },
        ],
    },
];

const footer: Item[] = [
    { title: 'Browse auctions', href: '/auctions', icon: Search },
    { title: 'Back to website', href: '/', icon: House, exact: true },
];

export function AccountSidebar() {
    const path = usePage().url.split('?')[0];
    const active = (item: Item) =>
        (item.exact
            ? path === item.href
            : path === item.href || path.startsWith(`${item.href}/`)) ||
        (item.href === '/settings/profile' && path.startsWith('/settings/'));

    const menu = (items: Item[]) => (
        <SidebarMenu>
            {items.map((item) => (
                <SidebarMenuItem key={item.href}>
                    <SidebarMenuButton
                        asChild
                        isActive={active(item)}
                        tooltip={{ children: item.title }}
                    >
                        <Link href={item.href} prefetch>
                            <item.icon />
                            <span>{item.title}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            ))}
        </SidebarMenu>
    );

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/account" prefetch>
                                <AppLogo label="My account" />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {groups.map((g) => (
                    <SidebarGroup key={g.label ?? 'top'} className="px-2 py-0">
                        {g.label && (
                            <SidebarGroupLabel>{g.label}</SidebarGroupLabel>
                        )}
                        {menu(g.items)}
                    </SidebarGroup>
                ))}
            </SidebarContent>

            <SidebarFooter>
                <SidebarGroup className="px-0 py-0">
                    {menu(footer)}
                </SidebarGroup>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
