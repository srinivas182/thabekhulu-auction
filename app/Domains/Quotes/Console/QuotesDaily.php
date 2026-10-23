<?php

namespace App\Domains\Quotes\Console;

use App\Domains\Quotes\Actions\QuoteWorkflow;
use Illuminate\Console\Command;

class QuotesDaily extends Command
{
    /** @var string */
    protected $signature = 'quotes:daily';

    /** @var string */
    protected $description = 'Expire open quotations past their date and send reminders before expiry';

    public function handle(QuoteWorkflow $workflow): int
    {
        $result = $workflow->daily();
        $this->info("{$result['expired']} expired, {$result['reminded']} reminded.");

        return self::SUCCESS;
    }
}
