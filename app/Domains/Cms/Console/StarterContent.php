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

    protected $description = 'Fill About and How it works with starter text, and add Home to the header menu';

    public function handle(): int
    {
        foreach (['about' => self::about(), 'how-it-works' => self::howItWorks()] as $slug => $blocks) {
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
        Audit::record('cms', 'starter_content', 'Starter text added to About and How it works');

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
}
