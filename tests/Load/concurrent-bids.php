<?php

/*
 * Concurrency test for the bid engine (sprint 6 spec 10, decision B4).
 *
 * Forks N real PHP processes that bid on one listing at the same time, on
 * MySQL, with the auction inside its soft-close window, then checks the
 * invariants that must hold however the bids interleave.
 *
 * Run against a scratch MySQL database (it is wiped):
 *   DB_CONNECTION=mysql DB_DATABASE=stress php tests/Load/concurrent-bids.php [processes] [bids-per-process]
 */

use App\Domains\Bidding\Actions\AuctionCloser;
use App\Domains\Bidding\Actions\BidEngine;
use App\Domains\Bidding\Models\AuctionState;
use App\Domains\Bidding\Models\Bid;
use App\Domains\Bidding\Support\Increments;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Actions\DepositService;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Support\DepositReference;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\Screening;
use App\Domains\Verification\Models\Verification;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$processes = (int) ($argv[1] ?? 40);
$perProcess = (int) ($argv[2] ?? 25);
if (DB::connection()->getDriverName() !== 'mysql' || ! str_contains((string) config('database.connections.mysql.database'), 'stress')) {
    fwrite(STDERR, "Refusing to run: use a MySQL database whose name contains 'stress' (it is wiped).\n");
    exit(1);
}

// --- set up: one live listing, N approved bidders with received deposits -------------------
Artisan::call('migrate:fresh', ['--force' => true]);
Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\AccessSeeder', '--force' => true]);
Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\CatalogueSeeder', '--force' => true]);
config(['mail.default' => 'array']);

$listing = Listing::create([
    'category_id' => Category::where('slug', 'homes')->value('id'), 'city_id' => City::where('slug', 'emalahleni')->value('id'),
    'title' => 'Stress lot', 'slug' => 'stress-lot', 'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000,
    'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied', 'auction_type' => 'open',
    'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addSeconds(4),
]);
$users = [];
for ($i = 1; $i <= $processes; $i++) {
    $u = User::factory()->create(['type' => 'buyer', 'first_name' => "Bidder{$i}", 'last_name' => 'Stress']);
    $ap = BidderApproval::create(['listing_id' => $listing->id, 'user_id' => $u->id, 'status' => 'approved', 'bidder_number' => (string) (1000 + $i)]);
    Verification::create(['user_id' => $u->id, 'status' => 'verified', 'document_type' => 'sa_id', 'verified_at' => now(), 'expires_at' => now()->addYear()]);
    Screening::create(['user_id' => $u->id, 'result' => 'clear', 'lists' => 'FIC TFS', 'screened_at' => now()]);
    $d = Deposit::create(['listing_id' => $listing->id, 'user_id' => $u->id, 'bidder_approval_id' => $ap->id, 'amount' => 5000000, 'reference' => DepositReference::make((string) (1000 + $i)), 'status' => 'awaiting']);
    app(DepositService::class)->receive($d, 5000000, 'bank_import', "stress {$i}");
    $users[] = $u->id;
}
BidEngine::state($listing)->update(['ends_at' => now()->addSeconds(4)]); // every bid lands inside the soft-close window
$out = sys_get_temp_dir().'/bid-latency-'.getmypid();
@mkdir($out);
DB::disconnect(); // children open their own connections

// --- bid: N processes at once -------------------------------------------------------------
$start = microtime(true);
$children = [];
foreach ($users as $n => $userId) {
    $pid = pcntl_fork();
    if ($pid === 0) {
        DB::reconnect();
        $engine = app(BidEngine::class);
        $user = User::findOrFail($userId);
        $times = [];
        $accepted = 0;
        mt_srand(getmypid());
        for ($k = 0; $k < $perProcess; $k++) {
            $state = AuctionState::findOrFail($listing->id);
            $next = Increments::next($state->current_amount, 1000000);
            $amount = $next + Increments::at($next) * mt_rand(0, 2);
            $max = mt_rand(0, 4) === 0 ? $amount + Increments::at($amount) * mt_rand(1, 6) : null;
            $t = microtime(true);
            try {
                $engine->place($listing, $user, $amount, $max, '10.1.'.intdiv($n, 250).'.'.($n % 250));
                $accepted++;
            } catch (ValidationException) {
                // outbid while deciding, or already leading: expected under contention
            }
            $times[] = (microtime(true) - $t) * 1000;
        }
        file_put_contents("{$out}/{$userId}", json_encode(['times' => $times, 'accepted' => $accepted]));
        exit(0);
    }
    $children[] = $pid;
}
foreach ($children as $pid) {
    pcntl_waitpid($pid, $status);
}
$elapsed = microtime(true) - $start;

// --- verify --------------------------------------------------------------------------------
DB::reconnect();
$bids = Bid::where('listing_id', $listing->id)->orderBy('id')->get();
$state = AuctionState::findOrFail($listing->id);
$failures = [];
$check = function (bool $ok, string $what) use (&$failures) {
    if (! $ok) {
        $failures[] = $what;
    } echo ($ok ? '  PASS ' : '  FAIL ').$what."\n";
};

$prev = 0;
$monotonic = true;
foreach ($bids as $b) {
    if ($b->amount < $prev) {
        $monotonic = false;
    }
    $prev = $b->amount;
}
$check($monotonic, 'bid amounts never go down in the order they were recorded');
$check($bids->every(fn ($b) => $b->amount >= 1000000), 'every bid is at or above the minimum');
$check($state->current_amount === $bids->max('amount'), 'current bid equals the highest recorded bid');
$check($state->leader_approval_id === $bids->last()->bidder_approval_id, 'leader is the bidder of the last recorded bid');
$check($state->bid_count === $bids->count(), 'bid count matches the bids recorded');
$check($state->extensions >= 1 && $state->ends_at->greaterThanOrEqualTo($bids->max('placed_at')->addMinutes(5)->subSecond()), 'soft close extended the end to 5 minutes after the last bid');

$state->update(['ends_at' => now()->subSecond()]);
$listing->update(['ends_at' => now()->subSecond()]);
app(AuctionCloser::class)->close($listing->fresh());
$result = DB::table('auction_results')->where('listing_id', $listing->id)->first();
$check($result !== null && (int) $result->winner_approval_id === $state->leader_approval_id && (int) $result->winning_amount === $state->current_amount, 'the close records exactly that leader and amount as the winner');
$check(DB::table('refunds')->count() === $processes - 1, 'every other bidder\'s deposit is scheduled for refund (or held as runner-up)');

$times = [];
$accepted = 0;
foreach (glob("{$out}/*") as $f) {
    $d = json_decode((string) file_get_contents($f), true);
    $times = array_merge($times, $d['times']);
    $accepted += $d['accepted'];
}
sort($times);
$p = fn (float $q) => round($times[(int) floor($q * (count($times) - 1))], 1);
printf("\n%d processes x %d attempts = %d bid calls in %.1fs; %d accepted, %d refused under contention, %d bids recorded (incl. automatic)\n",
    $processes, $perProcess, count($times), $elapsed, $accepted, count($times) - $accepted, $bids->count());
printf("bid latency ms: p50 %s, p95 %s, max %s\n", $p(0.5), $p(0.95), $p(1.0));
echo $failures === [] ? "\nALL INVARIANTS HOLD\n" : "\n".count($failures)." INVARIANT(S) FAILED\n";
exit($failures === [] ? 0 : 1);
