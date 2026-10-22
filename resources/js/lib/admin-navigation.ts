import {
    History,
    Palette,
    FolderTree,
    LayoutGrid,
    MapPin,
    Settings,
    ShieldCheck,
    ToggleRight,
    Users,
} from 'lucide-react';
import { dashboard } from '@/routes/admin';
import { index as audit } from '@/routes/admin/audit';
import { index as categories } from '@/routes/admin/categories';
import { index as features } from '@/routes/admin/features';
import { index as locations } from '@/routes/admin/locations';
import { index as roles } from '@/routes/admin/roles';
import { edit as settings } from '@/routes/admin/settings';
import { edit as theme } from '@/routes/admin/theme';
import { index as staff } from '@/routes/admin/staff';
import type { NavItem } from '@/types';

/**
 * Back-office navigation. Each item names the permission needed to see it;
 * later sprints add their modules here.
 */
export const adminNavigation: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
        permission: 'dashboard.view',
    },
    {
        title: 'Categories',
        href: categories(),
        icon: FolderTree,
        permission: 'categories.manage',
    },
    {
        title: 'Locations',
        href: locations(),
        icon: MapPin,
        permission: 'categories.manage',
    },
    {
        title: 'Staff',
        href: staff(),
        icon: Users,
        permission: 'staff.view',
    },
    {
        title: 'Roles',
        href: roles(),
        icon: ShieldCheck,
        permission: 'roles.view',
    },
    {
        title: 'Settings',
        href: settings(),
        icon: Settings,
        permission: 'settings.view',
    },
    {
        title: 'Theme',
        href: theme('website'),
        icon: Palette,
        permission: 'theme.manage',
    },
    {
        title: 'Feature flags',
        href: features(),
        icon: ToggleRight,
        permission: 'features.manage',
    },
    {
        title: 'Audit log',
        href: audit(),
        icon: History,
        permission: 'audit.view',
    },
];
