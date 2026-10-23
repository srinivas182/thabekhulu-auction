import {
    History,
    House,
    IdCard,
    Inbox,
    ReceiptText,
    ShieldCheck,
    MessageSquare,
    Palette,
    FileLock,
    FileText,
    FolderTree,
    LayoutGrid,
    MapPin,
    Settings,
    ToggleRight,
    Users,
} from 'lucide-react';
import { dashboard } from '@/routes/admin';
import { index as audit } from '@/routes/admin/audit';
import { index as categories } from '@/routes/admin/categories';
import { pages as cmsPages } from '@/routes/admin/cms';
import { settings as dataRoomSettings } from '@/routes/admin/dataroom';
import { index as enquiries } from '@/routes/admin/enquiries';
import { index as features } from '@/routes/admin/features';
import { index as leads } from '@/routes/admin/leads';
import { index as kyc } from '@/routes/admin/kyc';
import { index as listings } from '@/routes/admin/listings';
import { index as quotes } from '@/routes/admin/quotes';
import { providers as verificationProviders } from '@/routes/admin/verification';
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
        title: 'Listings',
        href: listings(),
        icon: House,
        permission: 'listings.view',
    },
    {
        title: 'KYC review',
        href: kyc(),
        icon: IdCard,
        permission: 'kyc.view',
    },
    {
        title: 'Quotations',
        href: quotes(),
        icon: ReceiptText,
        permission: 'quotes.create',
    },
    {
        title: 'Seller leads',
        href: leads(),
        icon: Inbox,
        permission: 'leads.view',
    },
    {
        title: 'Enquiries',
        href: enquiries(),
        icon: MessageSquare,
        permission: 'enquiries.view',
    },
    {
        title: 'Website content',
        href: cmsPages(),
        icon: FileText,
        permission: 'cms.manage',
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
        title: 'Verification',
        href: verificationProviders(),
        icon: ShieldCheck,
        permission: 'verification.providers',
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
        title: 'Data room',
        href: dataRoomSettings(),
        icon: FileLock,
        permission: 'settings.manage',
    },
    {
        title: 'Audit log',
        href: audit(),
        icon: History,
        permission: 'audit.view',
    },
];
