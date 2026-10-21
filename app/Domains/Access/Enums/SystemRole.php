<?php

namespace App\Domains\Access\Enums;

/**
 * Roles seeded with the platform. They cannot be renamed or deleted;
 * super admins can create additional custom staff roles.
 */
enum SystemRole: string
{
    case SuperAdmin = 'super-admin';
    case Auctioneer = 'auctioneer';
    case ListingsManager = 'listings-manager';
    case KycOfficer = 'kyc-officer';
    case Finance = 'finance';
    case ContentEditor = 'content-editor';
    case Support = 'support';
    case Buyer = 'buyer';
    case Seller = 'seller';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super admin',
            self::Auctioneer => 'Auctioneer',
            self::ListingsManager => 'Listings manager',
            self::KycOfficer => 'KYC officer',
            self::Finance => 'Finance',
            self::ContentEditor => 'Content editor',
            self::Support => 'Support',
            self::Buyer => 'Buyer',
            self::Seller => 'Seller',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Full access, including settings, roles, fees and gateways.',
            self::Auctioneer => 'Approves and publishes auctions, monitors live bidding, confirms winners and handles defaults.',
            self::ListingsManager => 'Creates and edits listings, media and documents, and submits them for review.',
            self::KycOfficer => 'Reviews buyer verification and applies suspensions.',
            self::Finance => 'Reconciles deposits, issues refunds, records seller payouts and runs financial reports.',
            self::ContentEditor => 'Manages pages, blog, gallery, events and menus.',
            self::Support => 'Answers buyer and seller enquiries; no financial or approval rights.',
            self::Buyer => 'Registered buyer account (website only).',
            self::Seller => 'Seller account (website only; switched off in Phase 1).',
        };
    }

    /** Staff roles can be given to back-office users; account roles cannot. */
    public function isStaffRole(): bool
    {
        return ! in_array($this, [self::Buyer, self::Seller], true);
    }
}
