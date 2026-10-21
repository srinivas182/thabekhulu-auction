import { LayoutGrid, ShieldCheck, Users } from 'lucide-react';
import { dashboard } from '@/routes/admin';
import { index as roles } from '@/routes/admin/roles';
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
];
