<?php

namespace Database\Seeders;

use App\Domains\Quotes\Models\QuoteTemplate;
use Illuminate\Database\Seeder;

/**
 * Three starter templates. Wording and line items are placeholders marked
 * "needs review" until Thabekhulu supplies their own; prices are left at
 * zero rather than invented.
 */
class QuoteSeeder extends Seeder
{
    public function run(): void
    {
        $review = '<p><strong>[Wording to be supplied by Thabekhulu and reviewed by their attorney]</strong></p>';
        $item = fn (string $d, string $unit = 'each', string $f = 'once') => ['kind' => 'item', 'frequency' => $f, 'description' => $d, 'quantity' => 100, 'unit' => $unit, 'unit_price' => 0, 'vat_applicable' => true];

        $templates = [
            'Renovation' => [
                'lines' => [$item('Site assessment and specification'), $item('Renovation works', 'job'), $item('Project management', 'month')],
                'milestones' => [['name' => 'Site assessment'], ['name' => 'Renovation works'], ['name' => 'Handover']],
                'payments' => [['label' => 'Deposit on acceptance', 'percent_bps' => 5000], ['label' => 'On completion', 'percent_bps' => 5000]],
            ],
            'Mining due diligence' => [
                'lines' => [$item('Review of mining or prospecting right and title'), $item('Environmental authorisation and water use review'), $item('Competent person’s report review'), $item('Data room administration', 'month', 'monthly')],
                'milestones' => [['name' => 'Document collection'], ['name' => 'Technical and legal review'], ['name' => 'Report to investor']],
                'payments' => [['label' => 'Deposit on acceptance', 'percent_bps' => 5000], ['label' => 'On delivery of the report', 'percent_bps' => 5000]],
            ],
            'Development project' => [
                'lines' => [$item('Feasibility study'), $item('Design and approvals'), $item('Construction management', 'month'), $item('Property management after completion', 'month', 'monthly')],
                'milestones' => [['name' => 'Feasibility'], ['name' => 'Design and approvals'], ['name' => 'Construction'], ['name' => 'Handover']],
                'payments' => [['label' => 'On acceptance', 'percent_bps' => 3000], ['label' => 'On approvals', 'percent_bps' => 3000], ['label' => 'On handover', 'percent_bps' => 4000]],
            ],
        ];

        foreach ($templates as $name => $content) {
            QuoteTemplate::firstOrCreate(['name' => $name], ['needs_review' => true, 'content' => $content + [
                'scope' => $review, 'assumptions' => $review, 'exclusions' => $review, 'terms' => $review,
            ]]);
        }
    }
}
