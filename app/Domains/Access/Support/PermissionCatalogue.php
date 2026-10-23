<?php

namespace App\Domains\Access\Support;

use App\Domains\Access\Enums\SystemRole;

/**
 * The full Phase 1 permission list, named "module.action", and the
 * default permissions of each system role. Sprint 2b seeds everything so
 * later sprints only attach policies to existing permissions.
 */
final class PermissionCatalogue
{
    /**
     * Module => [label, [action => description]].
     *
     * @var array<string, array{0: string, 1: array<string, string>}>
     */
    public const MODULES = [
        'dashboard' => ['Dashboard', ['view' => 'View the back-office dashboard']],
        'staff' => ['Staff', ['view' => 'View staff', 'invite' => 'Invite staff and change their role', 'deactivate' => 'Deactivate and reactivate staff']],
        'roles' => ['Roles', ['view' => 'View roles', 'manage' => 'Create, edit and delete custom roles']],
        'settings' => ['Settings', ['view' => 'View settings', 'manage' => 'Change settings']],
        'theme' => ['Theme', ['manage' => 'Change website and back-office theme']],
        'audit' => ['Audit log', ['view' => 'View the audit log', 'export' => 'Export the audit log']],
        'features' => ['Feature flags', ['manage' => 'Switch features on and off']],
        'categories' => ['Categories', ['manage' => 'Manage categories and document types']],
        'compliance' => ['Compliance', ['signoff' => 'Sign off listing compliance checklist items']],
        'listings' => ['Listings', ['view' => 'View listings', 'create' => 'Create listings', 'edit' => 'Edit listings', 'submit' => 'Submit listings for review', 'publish' => 'Approve and publish listings', 'delete' => 'Delete draft listings']],
        'auctions' => ['Auctions', ['monitor' => 'Monitor live auctions', 'pause' => 'Pause an auction', 'settle' => 'Confirm winners and handle defaults']],
        'verification' => ['Verification providers', ['providers' => 'Enable verification providers, enter their keys and route checks']],
        'kyc' => ['KYC', ['view' => 'View verification submissions', 'approve' => 'Approve verification', 'reject' => 'Reject verification', 'suspend' => 'Suspend bidding', 'approve_company' => 'Verify company and trust bidders']],
        'bidders' => ['Bidders', ['approve' => 'Approve bidders for a listing']],
        'buyers' => ['Buyers', ['view' => 'View buyers', 'manage' => 'Edit buyer accounts']],
        'sellers' => ['Sellers', ['view' => 'View sellers', 'manage' => 'Edit seller accounts']],
        'deposits' => ['Deposits', ['view' => 'View deposits', 'reconcile' => 'Reconcile manual payments']],
        'refunds' => ['Refunds', ['view' => 'View refunds', 'issue' => 'Issue refunds']],
        'payouts' => ['Seller payouts', ['view' => 'View payouts', 'record' => 'Record payouts']],
        'invoices' => ['Invoices', ['view' => 'View invoices']],
        'reports' => ['Reports', ['view' => 'View reports', 'export' => 'Export reports']],
        'cms' => ['Website content', ['manage' => 'Manage pages, blog, gallery and menus']],
        'events' => ['Events', ['manage' => 'Manage events', 'checkin' => 'Check in event tickets']],
        'enquiries' => ['Enquiries', ['view' => 'View enquiries', 'respond' => 'Respond to enquiries']],
        'leads' => ['Seller leads', ['view' => 'View "Sell with us" leads', 'manage' => 'Manage leads']],
        'premium' => ['Premium slots', ['manage' => 'Assign premium slots']],
        'quotes' => ['Quotations', ['create' => 'Create and edit quotations', 'approve' => 'Approve quotations above the threshold', 'send' => 'Send quotations to clients', 'view_all' => 'See every quotation, not only your own', 'settings' => 'Change quotation settings, catalogue and templates']],
        'support' => ['Support', ['view' => 'View support tickets', 'respond' => 'Respond to support tickets']],
    ];

    /** @return list<string> */
    public static function all(): array
    {
        $names = [];

        foreach (self::MODULES as $module => [, $actions]) {
            foreach (array_keys($actions) as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return $names;
    }

    /**
     * Permissions grouped for the role editor.
     *
     * @return list<array{module: string, label: string, permissions: list<array{name: string, description: string}>}>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::MODULES as $module => [$label, $actions]) {
            $permissions = [];

            foreach ($actions as $action => $description) {
                $permissions[] = ['name' => "{$module}.{$action}", 'description' => $description];
            }

            $groups[] = ['module' => $module, 'label' => $label, 'permissions' => $permissions];
        }

        return $groups;
    }

    /**
     * Default permissions per system role. Super admin also bypasses all
     * checks through a gate.
     *
     * @return list<string>
     */
    public static function forRole(SystemRole $role): array
    {
        return match ($role) {
            SystemRole::SuperAdmin => self::all(),
            SystemRole::Auctioneer => [
                'dashboard.view', 'listings.view', 'listings.publish', 'compliance.signoff', 'auctions.monitor', 'auctions.pause',
                'auctions.settle', 'buyers.view', 'deposits.view', 'reports.view', 'premium.manage', 'leads.view',
                'quotes.create', 'quotes.send', 'bidders.approve', 'kyc.view',
            ],
            SystemRole::ListingsManager => [
                'dashboard.view', 'listings.view', 'listings.create', 'listings.edit', 'listings.submit',
                'listings.delete', 'leads.view', 'leads.manage', 'enquiries.view', 'quotes.create', 'quotes.send',
            ],
            SystemRole::KycOfficer => [
                'dashboard.view', 'kyc.view', 'kyc.approve', 'kyc.reject', 'kyc.suspend', 'kyc.approve_company', 'buyers.view',
            ],
            SystemRole::Finance => [
                'dashboard.view', 'deposits.view', 'deposits.reconcile', 'refunds.view', 'refunds.issue',
                'payouts.view', 'payouts.record', 'invoices.view', 'reports.view', 'reports.export',
                'buyers.view', 'sellers.view', 'quotes.create', 'quotes.approve', 'quotes.send', 'quotes.view_all',
            ],
            SystemRole::ContentEditor => [
                'dashboard.view', 'cms.manage', 'events.manage', 'events.checkin',
            ],
            SystemRole::Support => [
                'dashboard.view', 'support.view', 'support.respond', 'enquiries.view', 'enquiries.respond',
                'buyers.view', 'sellers.view', 'listings.view', 'events.checkin',
            ],
            SystemRole::Buyer, SystemRole::Seller => [],
        };
    }
}
