<?php

namespace App\Domains\Cms\Console;

use App\Domains\Audit\Support\Audit;
use App\Domains\Cms\Models\MenuItem;
use App\Domains\Cms\Models\Page;
use App\Domains\Cms\Support\Blocks;
use App\Domains\Cms\Support\SiteNavigation;
use Illuminate\Console\Command;

/**
 * Professional starter text for the About and How it works pages, and a Home
 * link in the header. Only fills pages still marked "needs content", so it
 * never overwrites edited text, and never touches the legal pages. The pages
 * stay marked for Thabekhulu to review the wording.
 */
class StarterContent extends Command
{
    protected $signature = 'content:starter';

    // Company details used across the legal and contact pages.
    public const COMPANY = 'Thabekhulu Development Group (Pty) Ltd';

    public const ADDRESS = '6th Floor, Royal Towers, 30 Dorothy Nyembe Street, Durban, 4001';

    public const EMAIL = 'admin@stevemaqueens.co.za';

    public const PHONE = '+27 61 961 1898';

    public const OFFICER = 'Nonstikelelo';

    protected $description = 'Fill About and How it works with starter text, and add Home to the header menu';

    public function handle(): int
    {
        $pages = ['about' => self::about(), 'how-it-works' => self::howItWorks(), 'buyers-guide' => self::buyersGuide(), 'rules-of-auction' => self::rulesOfAuction(),
            'fees' => self::fees(), 'privacy' => self::privacy(), 'paia-manual' => self::paiaManual(), 'disclaimer' => self::disclaimer(), 'contact' => self::contact()];
        foreach ($pages as $slug => $blocks) {
            $page = Page::where('slug', $slug)->first();
            if ($page === null) {
                $this->warn("No {$slug} page found.");

                continue;
            }
            if (! $page->needs_content) {
                $this->line("Kept {$slug}: it has been edited.");

                continue;
            }
            $page->update(['blocks' => Blocks::clean($blocks, $page), 'needs_content' => true]);
            $this->info("Filled {$slug} (still marked for review in Website content).");
        }

        if (! MenuItem::where('menu', 'header')->where('url', '/')->exists()) {
            // Make room at the front (sort_order is unsigned in MySQL, so no negative numbers).
            MenuItem::where('menu', 'header')->increment('sort_order');
            MenuItem::create(['menu' => 'header', 'label' => 'Home', 'url' => '/', 'sort_order' => 0]);
            $this->info('Added Home to the header menu.');
        }
        SiteNavigation::forget(); // menus are cached; refresh them as the back office does
        Audit::record('cms', 'starter_content', 'Starter text added to pages still marked as needing content');

        return self::SUCCESS;
    }

    /** @return list<array<string, mixed>> */
    public static function about(): array
    {
        return [
            ['type' => 'rich_text', 'html' => '<h2>Property and mining auctions, run openly online</h2>'
                .'<p>Thabekhulu Development Group is a South African property group. We develop and renovate property, and we sell homes, land, mining rights and commercial property through open online auctions that buyers can join from anywhere.</p>'
                .'<p>Every auction follows published Rules of Auction. Bidders are verified before they bid, deposits are handled by the book, and the documents for each property are ready before bidding opens, so buyers can make decisions with the facts in front of them.</p>'],
            ['type' => 'two_column',
                'left' => '<h3>Homes and land</h3><p>Family homes, including homes renovated by Thabekhulu before sale, development stands, farms and agricultural land across South Africa.</p>',
                'right' => '<h3>Mining and commercial property</h3><p>Prospecting and mining rights, often sold by sealed tender, and income-producing property such as shopping centres with tenants in place.</p>'],
            ['type' => 'rich_text', 'html' => '<h2>Why buyers trust the process</h2><ul>'
                .'<li><strong>Verified bidders.</strong> Everyone who bids has confirmed their identity and been screened, as South African anti-money-laundering law requires.</li>'
                .'<li><strong>Deposits handled properly.</strong> Each deposit is matched to a unique reference, receipted, and refunded to bidders who don’t win.</li>'
                .'<li><strong>Open bidding.</strong> Every bid in an open auction is visible with its bidder number, and a late bid extends the auction so no one is outbid in the last second.</li>'
                .'<li><strong>Documents first.</strong> Title deeds, reports and other documents are available to approved bidders before they bid.</li>'
                .'<li><strong>A clear path after the auction.</strong> Winners see every step to transfer, with deadlines, in their account.</li></ul>'],
            ['type' => 'cta', 'title' => 'Ready to take part?', 'text' => 'Register once, verify your identity, and you can be approved for any auction that interests you.',
                'button_label' => 'Register to bid', 'button_url' => '/register'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function howItWorks(): array
    {
        return [
            ['type' => 'rich_text', 'html' => '<h2>From registration to transfer</h2><p>Buying at auction with Thabekhulu takes six steps. You can do all of them online, and your account shows what to do next at every stage.</p>'],
            ['type' => 'rich_text', 'html' => '<ol>'
                .'<li><h3>Register</h3><p>Create your account with your name, email address and mobile number, and confirm your email address.</p></li>'
                .'<li><h3>Verify your identity</h3><p>Upload your ID and take a quick selfie through our verification partner. To bid for a company or trust, add its registration documents and the people who control it.</p></li>'
                .'<li><h3>Get approved for an auction</h3><p>Choose a listing and request approval to bid. Some listings ask you to accept a confidentiality agreement before opening their documents, and larger auctions ask for proof of funds.</p></li>'
                .'<li><h3>Pay the deposit</h3><p>Each listing shows its deposit. Pay it by EFT using the unique reference we give you, or through our escrow partner where offered. You receive a receipt, and your bidder number is ready once the deposit is confirmed.</p></li>'
                .'<li><h3>Bid</h3><p>In an open auction you can bid as the auction runs, or set a maximum and let the system bid for you up to that amount. A bid in the final minutes extends the closing time, and large bids are confirmed with a one-time code. In a sealed tender you submit one confidential offer, opened after the closing time.</p></li>'
                .'<li><h3>After the auction</h3><p>If you win, you receive the next steps with their deadlines, and your deposit counts towards the purchase or is held as security, as the listing states. If you don’t win, your deposit is refunded to your verified bank account. For mining rights, the winning offer becomes the preferred bidder, and transfer depends on the Minister’s consent under section 11 of the MPRDA.</p></li>'
                .'</ol>'],
            ['type' => 'faq', 'items' => [
                ['question' => 'Are bids binding?', 'answer' => '<p>Yes. A bid is an offer to buy on the conditions of sale for that listing, so read them, and the Rules of Auction, before you bid.</p>'],
                ['question' => 'Can I bid for a company or trust?', 'answer' => '<p>Yes. Add the company or trust in your account with its documents. Once it is verified, you can be approved to bid on its behalf.</p>'],
                ['question' => 'Can I see the documents before I bid?', 'answer' => '<p>Yes. Once you are approved for a listing, its documents are available in the data room, so you can review them, or have your advisers review them, before bidding.</p>'],
                ['question' => 'When do I get my deposit back if I don’t win?', 'answer' => '<p>Refunds are processed after the auction closes and paid to the bank account you verified. You can follow the status in your account.</p>'],
                ['question' => 'What happens if a winner doesn’t complete the purchase?', 'answer' => '<p>The conditions of sale set out what happens, including what may be kept from the deposit. Thabekhulu may then offer the property to the next-highest bidder.</p>'],
                ['question' => 'What does it cost?', 'answer' => '<p>Costs differ by property. Each listing shows its deposit and terms, and the Fees page explains what buyers pay.</p>'],
            ]],
            ['type' => 'cta', 'title' => 'See what’s on auction', 'text' => 'Browse live and upcoming auctions for homes, land, mining rights and commercial property.',
                'button_label' => 'Browse auctions', 'button_url' => '/auctions'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function buyersGuide(): array
    {
        return [
            ['type' => 'rich_text', 'html' => '<h2>Buying at auction, step by step</h2><p>This guide explains what to expect, what to prepare and how to bid with confidence, whether it is your first auction or you buy for a company or trust.</p>'],
            ['type' => 'two_column',
                'left' => '<h3>Before you bid</h3><ul><li><strong>Read the listing.</strong> Check the minimum bid, the deposit, the closing date and the conditions of sale.</li><li><strong>Open the documents.</strong> Once approved, review the title deed, reports and other documents in the data room.</li><li><strong>View the property</strong> on a viewing day, or arrange one with us.</li><li><strong>Arrange your finance.</strong> Know how you will pay the balance or provide bank guarantees by the deadline.</li></ul>',
                'right' => '<h3>What you need</h3><ul><li>Your South African ID, or passport if you are not a South African citizen.</li><li>A recent proof of address (not older than three months).</li><li>A bank account in your name for deposit refunds, with a bank confirmation letter.</li><li>For a company or trust: registration documents and the people who control it.</li></ul>'],
            ['type' => 'rich_text', 'html' => '<h2>How bidding works</h2><ol>'
                .'<li><strong>Apply to bid</strong> on the listing. This registers you for that auction; it is not a bid.</li>'
                .'<li><strong>Get approved.</strong> We check your verification and give you a bidder number.</li>'
                .'<li><strong>Pay the deposit</strong> online, or by EFT with your unique reference. Bidding unlocks once it is confirmed.</li>'
                .'<li><strong>Bid while the auction is live.</strong> Every bid shows with its bidder number. You can set a maximum and let the system bid for you, only as much as needed.</li>'
                .'<li><strong>The highest bid wins</strong> when the auction closes. A bid in the final minutes extends the closing time, so nobody wins by bidding in the last second.</li></ol>'],
            ['type' => 'faq', 'items' => [
                ['question' => 'How much should I bid?', 'answer' => '<p>Decide your limit before the auction, including transfer costs, and stick to it. Setting it as your maximum lets the system bid for you up to that amount.</p>'],
                ['question' => 'Can I withdraw a bid?', 'answer' => '<p>No. Bids are binding offers under the Rules of Auction. You can raise your maximum, but not lower or withdraw a bid.</p>'],
                ['question' => 'What if I win?', 'answer' => '<p>The listing goes under offer and you receive the next steps: sign the sale agreement, then pay the balance or provide guarantees by the deadlines shown in your account.</p>'],
                ['question' => 'What if I don’t win?', 'answer' => '<p>Your deposit is refunded to your verified bank account after the auction closes.</p>'],
            ]],
            ['type' => 'cta', 'title' => 'Ready to start?', 'text' => 'Register once and verify your identity; then apply for any auction that interests you.', 'button_label' => 'Register to bid', 'button_url' => '/register'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function rulesOfAuction(): array
    {
        return [
            ['type' => 'rich_text', 'html' => '<p><em>Draft for review by Thabekhulu’s attorney.</em></p><p>These rules apply to every auction run by '.self::COMPANY.' (“the auctioneer”) on this website, as required by section 45 of the Consumer Protection Act 68 of 2008 and its regulations. The conditions of sale for each listing form part of these rules; if they conflict, the conditions of sale apply.</p>'],
            ['type' => 'rich_text', 'html' => '<h3>1. Registration and verification</h3><p>Only registered bidders may bid. Every bidder must verify their identity and be screened as the Financial Intelligence Centre Act requires, and be approved for each auction. A person bidding for a company, trust or another person must prove their authority to do so.</p>'
                .'<h3>2. Deposits</h3><p>Each listing states its deposit, which must be received before the bidder may bid. The deposit of the successful bidder counts towards the purchase price or is held as stated in the conditions of sale. Deposits of unsuccessful bidders are refunded to their verified bank accounts after the auction, except that the runner-up’s deposit may be held for the period stated in the conditions of sale.</p>'
                .'<h3>3. Bidding</h3><p>Every bid is an irrevocable offer to buy on the conditions of sale. Bids rise by at least the increment shown. Bidders may set a maximum, and the system then bids for them only as much as needed; maximums are confidential. Every bid in an open auction is shown with the bidder’s number. The auctioneer may refuse a bid that does not comply with these rules.</p>'
                .'<h3>4. Minimum price and seller bidding</h3><p>No bid below the stated minimum is accepted. The seller, or anyone on the seller’s behalf, may bid only if the conditions of sale for that listing expressly allow it and state the extent.</p>'
                .'<h3>5. Closing</h3><p>An auction closes at the time shown. A bid received in the final minutes extends the closing time, as shown on the listing. The highest valid bid when the auction closes is the successful bid.</p>'
                .'<h3>6. Sealed tenders</h3><p>Where a listing is sold by sealed tender, offers are confidential until the tender closes and are opened by two authorised staff members. Mining rights are sold subject to the approvals required by law, including consent under section 11 of the Mineral and Petroleum Resources Development Act.</p>'
                .'<h3>7. After the auction</h3><p>The successful bidder must sign the sale agreement and pay the balance or provide guarantees within the periods in the conditions of sale. If the successful bidder fails to do so, the auctioneer may offer the property to the runner-up at the runner-up’s bid, and the conditions of sale state what may be retained from the defaulting bidder’s deposit.</p>'
                .'<h3>8. Interruptions and disputes</h3><p>The auctioneer may pause an auction, extend it, or withdraw a listing before the sale is concluded, for example after a technical failure, and may void a bid placed in error, with reasons recorded. A record of all bids is kept. Disputes about bidding are decided by the auctioneer, without affecting any person’s rights under the Consumer Protection Act.</p>'
                .'<h3>9. Law</h3><p>These rules are governed by the law of the Republic of South Africa.</p>'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function fees(): array
    {
        return [
            ['type' => 'rich_text', 'html' => '<p>Every listing shows its deposit and terms before you bid. This page explains what buyers usually pay, so there are no surprises.</p>'],
            ['type' => 'two_column',
                'left' => '<h3>Paid through Thabekhulu</h3><ul><li><strong>Deposit:</strong> shown on each listing; it counts towards the price if you win, or is refunded if you don’t.</li><li><strong>Buyer’s commission:</strong> where a listing charges one, its percentage and any VAT are shown in that listing’s conditions of sale before you bid.</li><li>Registering, verifying your identity and bidding are free.</li></ul>',
                'right' => '<h3>Paid on transfer</h3><ul><li><strong>Transfer duty</strong> to SARS, or VAT where the seller is a VAT vendor.</li><li><strong>Conveyancing fees</strong> of the transferring attorney, and Deeds Office fees.</li><li><strong>Bond costs</strong> if you finance the purchase with a home loan.</li><li>Mining rights: costs of the section 11 application, where applicable.</li></ul>'],
            ['type' => 'faq', 'items' => [
                ['question' => 'When do I pay the deposit?', 'answer' => '<p>After you are approved for an auction and before you bid. Online payments are confirmed immediately; EFT once the payment reflects.</p>'],
                ['question' => 'How quickly are deposits refunded?', 'answer' => '<p>Unsuccessful bidders are refunded to their verified bank account after the auction closes, within the period stated in the conditions of sale.</p>'],
                ['question' => 'Who pays the seller’s costs?', 'answer' => '<p>The seller pays their own costs, such as rates clearance and agent or auction fees agreed with us, unless the conditions of sale say otherwise.</p>'],
            ]],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function privacy(): array
    {
        return [
            ['type' => 'rich_text', 'html' => '<p><em>Draft for review by Thabekhulu’s attorney.</em></p><p>'.self::COMPANY.', of '.self::ADDRESS.' (“we”), respects your privacy and processes personal information under the Protection of Personal Information Act 4 of 2013 (POPIA). This notice explains what we collect, why, and your rights.</p>'],
            ['type' => 'rich_text', 'html' => '<h3>What we collect</h3><ul><li>Account details: name, email address, mobile number and password (stored securely).</li><li>Verification records: identity document or passport, selfie, proof of address, marital status and, for companies and trusts, registration documents and the people who control them.</li><li>Bank details for refunds, and records of deposits, bids, payments and refunds.</li><li>Records of your use of the website, such as sign-ins, documents opened and enquiries.</li></ul>'
                .'<h3>Why we use it</h3><ul><li>To run auctions and sales, including approving bidders and processing deposits and refunds.</li><li>To meet our legal duties, including identity verification, screening and record-keeping under the Financial Intelligence Centre Act.</li><li>To keep the platform secure and prevent fraud.</li><li>To send you messages you asked for, such as auction alerts and newsletters, which you can stop at any time.</li></ul>'
                .'<h3>Who we share it with</h3><p>Only those who help us provide the service or whom the law requires: identity verification and payment providers, our banks and attorneys, transferring attorneys for a sale you win, and authorities such as the Financial Intelligence Centre where the law requires. Other bidders never see your name; bids are shown by bidder number only.</p>'
                .'<h3>How long we keep it</h3><p>Verification and transaction records are kept for at least five years after our relationship ends, as the Financial Intelligence Centre Act requires. Other information is kept only as long as needed, then deleted.</p>'
                .'<h3>How we protect it</h3><p>Documents are stored encrypted, access is limited to authorised staff and logged, and staff sign in with two-factor authentication.</p>'
                .'<h3>Your rights</h3><p>You may ask to access or correct your personal information, object to its processing, or ask us to delete it where the law allows. Contact our Information Officer, '.self::OFFICER.', at '.self::EMAIL.' or '.self::PHONE.'. You may also complain to the Information Regulator (inforegulator.org.za).</p>'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function paiaManual(): array
    {
        return [
            ['type' => 'rich_text', 'html' => '<p><em>Draft for review by Thabekhulu’s attorney.</em></p><p>This manual is published under section 51 of the Promotion of Access to Information Act 2 of 2000 (PAIA). It explains how to request records held by '.self::COMPANY.'.</p>'],
            ['type' => 'rich_text', 'html' => '<h3>1. Contact details</h3><p>Information Officer: '.self::OFFICER.' (Chief Executive Officer)<br>Email: '.self::EMAIL.'<br>Telephone: '.self::PHONE.'<br>Physical address: '.self::ADDRESS.'<br>Postal address: as for the physical address</p>'
                .'<h3>2. Guide by the Information Regulator</h3><p>The Information Regulator publishes a guide on how to use PAIA. It is available from the Information Regulator (inforegulator.org.za) and on request from our Information Officer.</p>'
                .'<h3>3. Records we hold</h3><ul><li>Company records: incorporation documents, statutory registers and minutes.</li><li>Financial records: financial statements, accounting records, tax records and banking records.</li><li>Auction and sale records: listings, conditions of sale, bidders’ records, deposits, refunds and settlement records.</li><li>Client records: verification and screening records, correspondence and agreements.</li><li>Employee records: employment contracts and payroll records.</li></ul>'
                .'<h3>4. Processing of personal information</h3><p>We process personal information of bidders, buyers, sellers, employees and suppliers for the purposes set out in our Privacy notice, and share it only as described there. Information may be stored with service providers outside South Africa only where POPIA permits.</p>'
                .'<h3>5. How to request a record</h3><p>Send a completed request form (Form 2, available from the Information Regulator) to our Information Officer, with proof of identity. A request may be refused on the grounds PAIA allows, for example to protect another person’s privacy or commercial information. We respond within 30 days.</p>'
                .'<h3>6. Fees</h3><p>Request and access fees are those prescribed in the PAIA regulations. We will tell you of any fee before processing your request.</p>'
                .'<h3>7. Availability</h3><p>This manual is available on our website and at our offices.</p>'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function disclaimer(): array
    {
        return [
            ['type' => 'rich_text', 'html' => '<p><em>Draft for review by Thabekhulu’s attorney.</em></p><p>We take care that the information on this website is accurate, but listings are prepared from information supplied by sellers and third parties. Please read this before you rely on anything here.</p>'],
            ['type' => 'rich_text', 'html' => '<h3>Listings</h3><p>Descriptions, sizes, photographs, zoning and other details are given in good faith but are not guaranteed. Bidders must inspect the property and review the documents in the data room before bidding. The conditions of sale for each listing apply.</p>'
                .'<h3>Mining rights</h3><p>A winning bid or tender does not by itself transfer a mining or prospecting right. Transfer requires the approvals required by law, including consent under section 11 of the Mineral and Petroleum Resources Development Act.</p>'
                .'<h3>No advice</h3><p>Nothing on this website is legal, tax, financial or investment advice. Get your own professional advice before you bid.</p>'
                .'<h3>Availability</h3><p>We aim to keep the website available, but cannot guarantee it is free of interruptions. The Rules of Auction explain what happens if an auction is interrupted.</p>'
                .'<h3>Liability</h3><p>To the extent the law allows, we are not liable for loss arising from reliance on information on this website, without affecting any rights you have under the Consumer Protection Act.</p>'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function contact(): array
    {
        return [
            ['type' => 'two_column',
                'left' => '<h3>Our office</h3><p>'.self::COMPANY.'<br>'.self::ADDRESS.'</p>',
                'right' => '<h3>Phone and email</h3><p>Telephone: <a href="tel:'.str_replace(' ', '', self::PHONE).'">'.self::PHONE.'</a><br>Email: <a href="mailto:'.self::EMAIL.'">'.self::EMAIL.'</a></p>'],
            ['type' => 'rich_text', 'html' => '<p>Questions about an auction, a listing, registering to bid or selling with us? Send us a message below and we’ll get back to you.</p>'],
        ];
    }
}
