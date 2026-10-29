<?php

namespace App\Domains\Platform\Email;

/**
 * Default wording for every fixed-message email, editable in Back office »
 * Email templates. Admins change words, never facts: links are always set by
 * the system, and each template's required details can't be removed.
 *
 * body = paragraphs before the button; after = paragraphs after it.
 * Paragraphs are separated by a blank line. Placeholders look like {{amount}}.
 */
final class EmailTemplates
{
    /** @return array<string, array{group: string, name: string, when: string, subject: string, greeting: string, body: string, after: string, button: string|null, required: list<string>, variables: array<string, string>, example: array<string, string>}> */
    public static function all(): array
    {
        $person = ['first_name' => 'The person’s first name'];
        $listing = ['listing' => 'The listing’s title'];
        $ex = ['first_name' => 'Thandi', 'listing' => 'Four-bedroom family home with pool'];

        return [
            // Accounts
            'account.verify_email' => self::t('Accounts', 'Confirm your email address', 'Sent after registering, when email verification is on.',
                'Confirm your email address', 'Hello {{first_name}},',
                'Thank you for registering with Thabekhulu auctions. Please confirm your email address to finish setting up your account.',
                'This link expires in {{expires_minutes}} minutes. If you didn’t create an account, you can ignore this email.', 'Confirm email address',
                [], $person + ['expires_minutes' => 'Minutes until the link expires'], $ex + ['expires_minutes' => '60']),
            'staff.invitation' => self::t('Accounts', 'Staff invitation', 'Sent when someone is invited to the back office.',
                'You’re invited to the Thabekhulu back office', 'Hello,',
                '{{inviter}} has invited you to join the Thabekhulu Development Group back office as {{role}}.',
                "This link expires in {{expires_hours}} hours and can be used once. You’ll choose a password when you accept.\n\nIf you weren’t expecting this invitation, you can ignore this email.", 'Accept invitation',
                ['inviter', 'role'], ['inviter' => 'Name of the person who invited them', 'role' => 'The role they’re invited to', 'expires_hours' => 'Hours until the link expires'],
                ['inviter' => 'Nomsa Khumalo', 'role' => 'Finance', 'expires_hours' => '72']),

            // Verification
            'kyc.verified' => self::t('Verification', 'Identity verified', 'Sent when a buyer’s identity is verified.',
                'Your identity is verified', 'Hello {{first_name}},',
                "Good news: your identity has been verified.\n\nNext, add the bank account we should refund deposits to. Once that’s verified, you can request approval to bid on any auction.",
                '', 'Add your bank account', [], $person, $ex),
            'kyc.rejected' => self::t('Verification', 'Identity not verified', 'Sent when identity verification fails.',
                'We couldn’t verify your identity', 'Hello {{first_name}},',
                "We weren’t able to verify your identity.\n\nReason: {{reason}}",
                'You can correct your details and submit them again. If you need help, reply to this email.', 'Update your details',
                ['reason'], $person + ['reason' => 'Why verification failed'], $ex + ['reason' => 'The photo of your ID was unclear.']),
            'kyc.more_info' => self::t('Verification', 'More information needed', 'Sent when the KYC team needs more information.',
                'We need a little more information', 'Hello {{first_name}},',
                "To finish verifying your identity, we need a little more information:\n\n{{reason}}",
                'Please update your details using the button above.', 'Update your details',
                ['reason'], $person + ['reason' => 'What the KYC team needs'], $ex + ['reason' => 'Please upload a proof of address dated within the last three months.']),
            'bank.verified' => self::t('Verification', 'Bank account verified', 'Sent when a refund bank account is verified.',
                'Your refund bank account is verified', 'Hello {{first_name}},',
                "Your refund bank account is verified. Any deposit refunds will be paid into this account.\n\nYou can now request approval to bid on the auctions that interest you.",
                '', null, [], $person, $ex),
            'bank.more_info' => self::t('Verification', 'Bank account: more information needed', 'Sent when the KYC team asks about a refund bank account.',
                'We need a little more about your bank account', 'Hello {{first_name}},',
                "Before we can verify your refund bank account, we need a little more:\n\n{{reason}}",
                'You can reply and submit a corrected letter on your Bank account page.', 'Update your bank account',
                ['reason'], $person + ['reason' => 'What the KYC team needs'], $ex + ['reason' => 'The bank letter is older than three months. Please upload one dated within the last three months.']),
            'bank.rejected' => self::t('Verification', 'Bank account not verified', 'Sent when a refund bank account can’t be verified.',
                'We couldn’t verify your bank account', 'Hello {{first_name}},',
                "We weren’t able to verify your refund bank account.\n\nReason: {{reason}}",
                'Please check the details and submit them again.', 'Update your bank account',
                ['reason'], $person + ['reason' => 'Why verification failed'], $ex + ['reason' => 'The account holder’s name doesn’t match your verified name.']),

            // Money
            'deposit.open' => self::t('Money', 'Deposits now open', 'Sent to approved bidders when a listing’s legal checks are complete and deposits open.',
                'You can now pay your deposit: {{listing}}', 'Hello {{first_name}},',
                'Our legal checks for {{listing}} are complete, so you can now pay your deposit. Once it’s confirmed, you can bid.',
                'Online payment is confirmed straight away; an EFT once it reflects in our account.', 'Pay deposit',
                [], $person + $listing, $ex),
            'deposit.received' => self::t('Money', 'Deposit received', 'Sent with the receipt when a deposit is confirmed.',
                'Deposit received: {{listing}}', 'Hello {{first_name}},',
                "We’ve received your deposit of R {{amount}} for {{listing}} (reference {{reference}}). Your receipt {{receipt_number}} is attached.\n\nYour refund bank account is now locked until this deposit is refunded or applied to your purchase.",
                'Thank you, and good luck with your bidding.', 'View your auctions',
                ['amount', 'reference', 'receipt_number'], $person + $listing + ['amount' => 'Amount received, for example 50 000.00', 'reference' => 'The deposit reference', 'receipt_number' => 'The receipt number'],
                $ex + ['amount' => '50 000.00', 'reference' => 'TDG-D-1234-AB7KQ', 'receipt_number' => 'TDG-R-2026-0042']),
            'deposit.refunded' => self::t('Money', 'Deposit refunded', 'Sent with the refund receipt when a refund is paid.',
                'Deposit refunded: {{listing}}', 'Hello {{first_name}},',
                "We’ve refunded R {{amount}} for deposit {{reference}} on {{listing}}. Your refund receipt {{receipt_number}} is attached.\n\nBank transfers usually reflect within one to three working days.",
                'Thank you for taking part. We hope to see you at a future auction.', null,
                ['amount', 'reference', 'receipt_number'], $person + $listing + ['amount' => 'Amount refunded', 'reference' => 'The deposit reference', 'receipt_number' => 'The refund receipt number'],
                $ex + ['amount' => '50 000.00', 'reference' => 'TDG-D-1234-AB7KQ', 'receipt_number' => 'TDG-R-2026-0057']),

            // Bidding
            'bid.code' => self::t('Bidding', 'Bid confirmation code', 'Sent when a large bid needs a one-time code.',
                'Your bid confirmation code: {{code}}', 'Hello {{first_name}},',
                "Your confirmation code is **{{code}}**.\n\nEnter it to confirm your bid of R {{amount}} on {{listing}}. The code expires in 10 minutes.",
                'If you didn’t try to place this bid, change your password straight away and contact us.', null,
                ['code', 'amount'], $person + $listing + ['code' => 'The one-time code', 'amount' => 'The bid amount'], $ex + ['code' => '482913', 'amount' => '3 450 000']),
            'auction.auto_bid' => self::t('Bidding', 'Automatic bid placed', 'Sent each time a bidder’s automatic bid (maximum) raises their bid to keep them in the lead.',
                'Your automatic bid raised your bid: {{listing}}', 'Hello {{first_name}},',
                "Someone else bid on {{listing}}, so your automatic bid raised your bid to {{amount}}. You’re still the highest bidder.\n\nYour maximum: {{maximum}}. We’ll only bid up to it, and tell you if you’re outbid.",
                'You can raise your maximum on the auction page at any time.', 'View the auction',
                ['amount'], $person + $listing + ['amount' => 'Your new bid', 'maximum' => 'Your maximum'], $ex + ['amount' => 'R 3 250 000', 'maximum' => 'R 3 500 000']),
            'auction.outbid' => self::t('Bidding', 'Outbid', 'Sent when someone bids higher (at most once every 10 minutes per auction).',
                'You’ve been outbid: {{listing}}', 'Hello {{first_name}},',
                'Another bidder has bid R {{amount}} on {{listing}}, so you’re no longer the highest bidder.',
                'If you’d like to stay in, you can bid again before the auction closes.', 'Bid again',
                ['amount'], $person + $listing + ['amount' => 'The new highest bid'], $ex + ['amount' => '3 300 000']),
            'auction.closing_soon' => self::t('Bidding', 'Closing soon', 'Sent to bidders shortly before an auction closes.',
                '{{listing}} closes in {{time_left}}', 'Hello {{first_name}},',
                "{{listing}} closes in {{time_left}}. {{position}}\n\nA bid in the final {{window_minutes}} minutes extends the closing time by {{extension_minutes}} minutes.",
                '', 'View the auction', [], $person + $listing + ['time_left' => 'For example 1 hour or 10 minutes', 'position' => 'Whether they’re the highest bidder', 'window_minutes' => 'Soft-close window, from Settings', 'extension_minutes' => 'Soft-close extension, from Settings'],
                $ex + ['time_left' => '1 hour', 'position' => 'You’re currently the highest bidder.', 'window_minutes' => '5', 'extension_minutes' => '5']),
            'auction.won' => self::t('Bidding', 'Winning bidder', 'Sent to the winning bidder when an auction closes.',
                'Congratulations: you’re the winning bidder for {{listing}}', 'Hello {{first_name}},',
                "Congratulations. Your bid of R {{amount}} is the winning bid for {{listing}}, subject to the Rules of Auction and the conditions of sale.\n\nOur team will contact you within one working day about the next steps, the documents to sign and the payment deadlines.",
                'You can follow every step in your account.', 'View the listing',
                ['amount'], $person + $listing + ['amount' => 'The winning bid'], $ex + ['amount' => '3 450 000']),
            'auction.runner_up' => self::t('Bidding', 'Second-highest bidder', 'Sent to the second-highest bidder when an auction closes.',
                'Auction result: {{listing}}', 'Hello {{first_name}},',
                "Thank you for bidding on {{listing}}. You were the second-highest bidder.\n\nYour deposit stays held until {{refund_date}} in case the sale to the winning bidder doesn’t go ahead. If it does go ahead, we’ll refund your deposit in full.",
                'We’ll contact you if the property becomes available to you.', null,
                ['refund_date'], $person + $listing + ['refund_date' => 'Date the deposit is held until'], $ex + ['refund_date' => '14 November 2026']),
            'auction.lost' => self::t('Bidding', 'Unsuccessful bid', 'Sent to the other bidders when an auction closes.',
                'Auction result: {{listing}}', 'Hello {{first_name}},',
                "Thank you for bidding on {{listing}}. Unfortunately your bid wasn’t successful this time.\n\nYour deposit is scheduled for refund on {{refund_date}}, to your verified bank account or through the escrow provider that holds it. We’ll email your refund receipt.",
                'We hope to see you at a future auction.', null,
                ['refund_date'], $person + $listing + ['refund_date' => 'Scheduled refund date'], $ex + ['refund_date' => '4 November 2026']),

            // Settlement
            'settlement.question' => self::t('Settlement', 'Question about a purchase', 'Sent when staff ask a winning bidder a question during settlement.',
                'A question about your purchase: {{listing}}', 'Hello {{first_name}},',
                "We have a question about your purchase of {{listing}}:\n\n{{question}}",
                'You can reply and upload any documents from your Purchases page. We’ll confirm once everything is in order.', 'Reply',
                ['question'], $person + $listing + ['question' => 'The question from our team'], $ex + ['question' => 'The signed agreement is missing your initials on page 3. Please initial it and upload it again.']),

            // Events
            'event.tickets' => self::t('Events', 'Event tickets', 'Sent with the tickets after booking an event.',
                'Your tickets: {{event}}', 'Hello {{name}},',
                "Thank you for booking. Here are your tickets for {{event}} on {{when}} (SAST).\n\n{{location}}",
                'We look forward to seeing you.', 'View your tickets',
                ['event', 'when'], ['name' => 'Name on the booking', 'event' => 'The event’s title', 'when' => 'Date and time', 'location' => 'Venue, or how to join online'],
                ['name' => 'Thandi Mokoena', 'event' => 'Investor briefing: mining tenders', 'when' => 'Thursday 12 November 2026, 08:00', 'location' => 'Venue: Sandton Convention Centre. Show the QR code at the door.']),

            // Quotations
            'quote.sent' => self::t('Quotations', 'Quotation sent', 'Sent with the quotation PDF.',
                'Quotation {{reference}}: {{title}}', 'Hello {{name}},',
                "{{message}}\n\nYou can view, download and accept the quotation online. It’s valid until {{valid_until}}.",
                '', 'View quotation', ['reference', 'valid_until'],
                ['name' => 'The client’s name', 'reference' => 'Quotation reference', 'title' => 'Quotation title', 'valid_until' => 'Valid-until date', 'message' => 'The message written when sending (or a standard line)'],
                ['name' => 'Sipho Dlamini', 'reference' => 'Q-2026-0018', 'title' => 'Renovation of 12 Oak Street', 'valid_until' => '30 November 2026', 'message' => 'Please find our quotation attached.']),
            'quote.reminder' => self::t('Quotations', 'Quotation reminder', 'Sent before a quotation expires.',
                'Reminder: quotation {{reference}} expires on {{valid_until}}', 'Hello {{name}},',
                'This is a friendly reminder that our quotation “{{title}}” is valid until {{valid_until}}.',
                'If you have any questions, just reply to this email.', 'View quotation', ['reference', 'valid_until'],
                ['name' => 'The client’s name', 'reference' => 'Quotation reference', 'title' => 'Quotation title', 'valid_until' => 'Valid-until date'],
                ['name' => 'Sipho Dlamini', 'reference' => 'Q-2026-0018', 'title' => 'Renovation of 12 Oak Street', 'valid_until' => '30 November 2026']),
            'quote.accepted' => self::t('Quotations', 'Quotation accepted', 'Sent with the signed copy after the client accepts.',
                'Accepted: quotation {{reference}}', 'Hello {{name}},',
                'Thank you for accepting our quotation “{{title}}”. A copy with your acceptance record is attached.',
                'We’ll be in touch about the next steps.', null, ['reference'],
                ['name' => 'The client’s name', 'reference' => 'Quotation reference', 'title' => 'Quotation title'],
                ['name' => 'Sipho Dlamini', 'reference' => 'Q-2026-0018', 'title' => 'Renovation of 12 Oak Street']),
        ];
    }

    /**
     * @param  list<string>  $required
     * @param  array<string, string>  $variables
     * @param  array<string, string>  $example
     * @return array{group: string, name: string, when: string, subject: string, greeting: string, body: string, after: string, button: string|null, required: list<string>, variables: array<string, string>, example: array<string, string>}
     */
    private static function t(string $group, string $name, string $when, string $subject, string $greeting, string $body, string $after, ?string $button, array $required, array $variables, array $example): array
    {
        return compact('group', 'name', 'when', 'subject', 'greeting', 'body', 'after', 'button', 'required', 'variables', 'example');
    }
}
