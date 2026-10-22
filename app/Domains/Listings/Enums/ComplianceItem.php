<?php

namespace App\Domains\Listings\Enums;

/**
 * Checklist that must be fully signed off before a listing can accept
 * deposits (sprint 3 spec revision 2; enforced in Sprint 5).
 */
enum ComplianceItem: string
{
    case SellerAuthority = 'seller_authority';
    case InterestDefined = 'interest_defined';
    case ApprovalsReviewed = 'approvals_reviewed';
    case TermsReviewed = 'terms_reviewed';
    case EscrowArranged = 'escrow_arranged';

    public function label(): string
    {
        return match ($this) {
            self::SellerAuthority => "Seller's authority to sell verified",
            self::InterestDefined => 'Exact interest being sold defined',
            self::ApprovalsReviewed => 'Mining right and environmental approvals reviewed (or not applicable)',
            self::TermsReviewed => 'Bidding conditions, sale agreement and refund terms legally reviewed',
            self::EscrowArranged => 'Payment or escrow arrangement in place',
        };
    }
}
