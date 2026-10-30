<?php

namespace App\Domains\Platform\Console;

use App\Domains\Audit\Support\Audit;
use App\Domains\Cms\Models\Gallery;
use App\Domains\Cms\Models\Post;
use App\Domains\Cms\Settings\HomeSettings;
use App\Domains\Cms\Support\BrandImages;
use App\Domains\Listings\Models\Listing;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * One-time launch reset: removes everything created while testing (listings, buyers and sellers,
 * KYC, bids, deposits, settlement, content, uploads) and keeps the platform's configuration (staff,
 * roles, settings, email templates, providers, categories, locations, pages, theme).
 *
 * Safety: shows a dry run unless --confirm is given; backs up the database and files first; refuses
 * if any table isn't classified below; records the launch and never runs again afterwards.
 */
class LaunchReset extends Command
{
    protected $signature = 'platform:launch-reset {--confirm= : Type "DELETE ALL TEST DATA" to run} {--no-backup : Skip the backup (testing only)}';

    protected $description = 'One-time reset before go-live: delete all test data, keep configuration';

    public const PHRASE = 'DELETE ALL TEST DATA';

    /** Configuration: kept as is. */
    public const KEEP = [
        'categories', 'category_attributes', 'cities', 'provinces', 'collections', 'catalogue_items', 'quote_templates', 'document_types',
        'email_templates', 'features', 'menu_items', 'migrations', 'pages', 'payment_providers', 'permissions', 'roles', 'role_has_permissions',
        'seller_packages', 'settings', 'theme_versions', 'verification_providers', 'verification_routes', 'platform_launches',
    ];

    /** Kept only for staff: rows belonging to buyers and sellers are removed. */
    public const STAFF_ONLY = ['users', 'model_has_roles', 'model_has_permissions', 'passkeys'];

    /** Media library: test content's files go (listings, articles, galleries); website page images stay. */
    public const PARTIAL = ['media'];

    /** @var list<class-string> */
    public const MEDIA_OF_TEST_CONTENT = [Listing::class, Post::class, Gallery::class];

    /** Test activity: emptied (numbering restarts). */
    public const DELETE = [
        'activity_log', 'advance_bids', 'auction_alerts', 'auction_results', 'auction_states', 'bank_accounts', 'bank_imports', 'bank_lines', 'bank_messages',
        'bid_otps', 'bidder_approvals', 'bidding_entities', 'bids', 'cache', 'cache_locks', 'clients', 'deposits', 'document_downloads', 'enquiries',
        'enquiry_replies', 'entity_documents', 'events', 'failed_jobs', 'galleries', 'invoice_sequences', 'invoices', 'job_batches', 'jobs',
        'lead_documents', 'lead_notes', 'leads', 'ledger_entries', 'listing_attribute_values', 'listing_compliance_attachments', 'listing_compliance_items',
        'listing_documents', 'listing_status_changes', 'listings', 'max_bids', 'nda_acceptances', 'newsletter_subscribers', 'password_reset_tokens',
        'payment_confirmations', 'payment_events', 'payouts', 'posts', 'premium_slots', 'proofs_of_funds', 'quote_events', 'quote_lines', 'quote_milestones',
        'quote_payments', 'quote_sequences', 'quotes', 'receipt_sequences', 'receipts', 'refund_batches', 'refunds', 'saved_search_hits', 'saved_searches',
        'screenings', 'security_approvals', 'seller_package_assignments', 'sessions', 'settlement_cases', 'settlement_defaults', 'settlement_messages', 'settlement_payments',
        'settlement_steps', 'settlement_uploads', 'staff_invitations', 'suspensions', 'suspicious_items', 'suspicious_notes', 'tender_offers', 'tender_openings',
        'ticket_orders', 'tickets', 'transfer_stages', 'verification_documents', 'verification_events', 'verification_messages', 'verifications', 'watchlist_items',
    ];

    public function handle(): int
    {
        if (Schema::hasTable('platform_launches') && DB::table('platform_launches')->exists()) {
            $when = DB::table('platform_launches')->value('reset_at');
            $this->error("The launch reset already ran ({$when}). It can only run once, so real data is never deleted.");

            return self::FAILURE;
        }

        $unclassified = self::unclassified();
        if ($unclassified !== []) {
            $this->error('These tables are not classified as keep or delete, so nothing was changed: '.implode(', ', $unclassified));

            return self::FAILURE;
        }

        $staff = DB::table('users')->where('type', 'staff')->pluck('id');
        /** @var list<int> $staffIds */
        $staffIds = array_values(array_map('intval', $staff->all()));
        $summary = $this->summary($staffIds);
        $this->info('The launch reset will delete:');
        foreach ($summary as $label => $count) {
            $this->line(sprintf('  %-34s %s', $label, $count));
        }
        $this->line('  Kept: '.$staff->count().' staff accounts, roles, settings, email templates, providers, categories, locations, pages and theme.');

        if ($this->option('confirm') !== self::PHRASE) {
            $this->newLine();
            $this->warn('Dry run: nothing was changed. To run it, add --confirm="'.self::PHRASE.'"');

            return self::SUCCESS;
        }

        $backup = null;
        if (! $this->option('no-backup')) {
            try {
                $backup = $this->backup();
                $this->info("Backup saved: {$backup}");
            } catch (Throwable $e) {
                $this->error('Backup failed, so nothing was deleted: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        // Keep the photos the site shows now (home banner, sign-in pages) before the listings they come from go.
        $kept = $this->keepSitePhotos();
        if ($kept !== []) {
            $this->info('Kept as site photos: '.implode(', ', $kept).'.');
        }
        // Media first, through the media library, so their files are removed too (website page images are kept).
        Media::query()->whereIn('model_type', self::MEDIA_OF_TEST_CONTENT)->each(fn ($m) => $m->delete());
        DB::transaction(fn () => $this->wipe($staffIds));
        // The private disk holds only uploads from testing (KYC documents, proofs, listing and settlement files).
        foreach (Storage::disk('local')->directories() as $dir) {
            Storage::disk('local')->deleteDirectory($dir);
        }

        $by = trim((string) (getenv('USER') ?: 'server')).' (terminal)';
        DB::table('platform_launches')->insert(['reset_at' => now(), 'reset_by' => $by, 'backup_path' => $backup, 'summary' => json_encode($summary)]);
        Audit::record('platform', 'launch_reset', 'Launch reset: all test data deleted before go-live', null, null, ['summary' => $summary, 'backup' => $backup, 'by' => $by]);

        $this->newLine();
        $this->info('Done. The platform is ready for launch, and this reset can’t run again.');
        $this->line('Next: php artisan security:check (two-factor and email verification should be on).');

        return self::SUCCESS;
    }

    /** @return list<string> */
    public static function unclassified(): array
    {
        $known = array_merge(self::KEEP, self::STAFF_ONLY, self::PARTIAL, self::DELETE);
        $tables = array_map(fn (array $t) => (string) $t['name'], Schema::getTables());

        return array_values(array_filter($tables, fn (string $t) => ! in_array($t, $known, true) && ! str_starts_with($t, 'sqlite_')));
    }

    /**
     * @param  list<int>  $staffIds
     * @return array<string, int>
     */
    private function summary(array $staffIds): array
    {
        $count = fn (string $t) => Schema::hasTable($t) ? DB::table($t)->count() : 0;

        return [
            'Listings' => $count('listings'),
            'Buyer and seller accounts' => DB::table('users')->whereNotIn('id', $staffIds)->count(),
            'Identity verifications' => $count('verifications'),
            'Bank accounts' => $count('bank_accounts'),
            'Bidder applications' => $count('bidder_approvals'),
            'Bids' => $count('bids'),
            'Deposits' => $count('deposits'),
            'Settlement cases' => $count('settlement_cases'),
            'Enquiries and seller leads' => $count('enquiries') + $count('leads'),
            'Articles, events and galleries' => $count('posts') + $count('events') + $count('galleries'),
            'Quotations' => $count('quotes'),
            'Audit trail entries' => $count('activity_log'),
            'Uploaded documents' => count(Storage::disk('local')->allFiles()),
            'Listing, article and gallery images' => Media::query()->whereIn('model_type', self::MEDIA_OF_TEST_CONTENT)->count(),
        ];
    }

    /** @param  list<int>  $staffIds */
    private function wipe(array $staffIds): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            foreach (self::deletionOrder() as $table) {
                if (Schema::hasTable($table)) {
                    // DELETE, not TRUNCATE: on MySQL, TRUNCATE commits implicitly and can't be rolled back.
                    // Receipt, invoice and quote numbers restart because their sequence tables are emptied.
                    DB::table($table)->delete();
                }
            }
            DB::table('model_has_roles')->where('model_type', User::class)->whereNotIn('model_id', $staffIds)->delete();
            DB::table('model_has_permissions')->where('model_type', User::class)->whereNotIn('model_id', $staffIds)->delete();
            DB::table('passkeys')->whereNotIn('user_id', $staffIds)->delete();
            DB::table('users')->whereNotIn('id', $staffIds)->delete();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /** Database dump plus an archive of uploaded files, kept outside the folders being cleared. */
    private function backup(): string
    {
        $dir = storage_path('launch-backup-'.now()->format('Ymd-His'));
        File::ensureDirectoryExists($dir);
        try {
            $connection = config('database.default');
            $db = config("database.connections.{$connection}");

            if (($db['driver'] ?? '') === 'sqlite') {
                File::copy((string) $db['database'], "{$dir}/database.sqlite");
            } else {
                $result = Process::env(['MYSQL_PWD' => (string) ($db['password'] ?? '')])->timeout(600)->run([
                    'mysqldump', '--single-transaction', '--routines', '-h', (string) $db['host'], '-P', (string) $db['port'], '-u', (string) $db['username'],
                    '--result-file='."{$dir}/database.sql", (string) $db['database'],
                ]);
                if (! $result->successful() || ! File::exists("{$dir}/database.sql") || File::size("{$dir}/database.sql") === 0) {
                    throw new \RuntimeException('mysqldump failed: '.trim($result->errorOutput()));
                }
            }

            $files = Process::timeout(600)->path(storage_path('app'))->run(['tar', '-czf', "{$dir}/files.tar.gz", '.']);
            if (! $files->successful()) {
                throw new \RuntimeException('file archive failed: '.trim($files->errorOutput()));
            }

        } catch (Throwable $e) {
            File::deleteDirectory($dir); // never leave a half-made backup (it could hold personal data)

            throw $e;
        }

        return $dir;
    }

    /**
     * The DELETE tables ordered so that a table is emptied before the tables it refers to, using the
     * database's own foreign keys. Works even where foreign-key checks can't be switched off
     * (SQLite inside a transaction); MySQL also has them switched off for the wipe.
     *
     * @return list<string>
     */
    public static function deletionOrder(): array
    {
        $tables = array_values(array_filter(self::DELETE, fn (string $t) => Schema::hasTable($t)));
        $parents = [];
        foreach ($tables as $t) {
            $parents[$t] = array_values(array_unique(array_filter(
                array_map(fn (array $fk) => (string) $fk['foreign_table'], Schema::getForeignKeys($t)),
                fn (string $p) => $p !== $t && in_array($p, $tables, true),
            )));
        }
        $ordered = [];
        $remaining = $tables;
        while ($remaining !== []) {
            // Next: tables no remaining table refers to (nothing still pointing at them).
            $free = array_values(array_filter($remaining, function (string $t) use ($remaining, $parents) {
                foreach ($remaining as $other) {
                    if ($other !== $t && in_array($t, $parents[$other] ?? [], true)) {
                        return false;
                    }
                }

                return true;
            }));
            if ($free === []) {
                $free = [$remaining[0]]; // a cycle: fall back to switched-off checks
            }
            $ordered = array_merge($ordered, $free);
            $remaining = array_values(array_diff($remaining, $free));
        }

        return $ordered;
    }

    /**
     * Copy the featured (or newest public) listing's photo into the home banner and sign-in photos when
     * none are set, so those pages look the same after the reset; clear the featured auction (it is deleted).
     *
     * @return list<string>
     */
    private function keepSitePhotos(): array
    {
        $home = app(HomeSettings::class);
        $query = Listing::public()->whereHas('media');
        $listing = ($home->featured_listing ? (clone $query)->where('ulid', $home->featured_listing)->first() : null) ?? $query->latest('id')->first();
        $media = $listing?->getFirstMedia('photos');
        $kept = [];
        if ($media !== null && is_readable($media->getPath())) {
            $contents = (string) file_get_contents($media->getPath());
            $extension = pathinfo($media->file_name, PATHINFO_EXTENSION) ?: 'jpg';
            foreach (['hero' => 'home banner', 'auth' => 'sign-in and sign-up'] as $kind => $label) {
                if (BrandImages::url($kind) === null) {
                    BrandImages::put($kind, $contents, $extension);
                    $kept[] = $label.' photo';
                }
            }
        }
        $home = app(HomeSettings::class);
        $home->refresh();
        $home->featured_listing = null;
        $home->save();

        return $kept;
    }
}
