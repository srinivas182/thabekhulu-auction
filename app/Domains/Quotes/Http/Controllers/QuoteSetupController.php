<?php

namespace App\Domains\Quotes\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Quotes\Models\CatalogueItem;
use App\Domains\Quotes\Models\Client;
use App\Domains\Quotes\Models\QuoteTemplate;
use App\Domains\Quotes\Settings\QuotationSettings;
use App\Domains\Quotes\Support\QuoteMath;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Clients (quotes.create), and catalogue, templates and quotation
 * settings (quotes.settings).
 */
class QuoteSetupController extends Controller
{
    public function clients(): Response
    {
        return Inertia::render('admin/quotes/clients', [
            'clients' => Client::withCount('quotes')->orderBy('name')->get()->map(fn (Client $c): array => $c->only(['id', 'name', 'company', 'email', 'phone', 'address', 'vat_number', 'notes']) + ['quotes' => (int) $c->getAttribute('quotes_count')]),
        ]);
    }

    public function saveClient(Request $request, #[CurrentUser] User $actor, ?Client $client = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'company' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'], 'vat_number' => ['nullable', 'string', 'max:30'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['email'] = strtolower($data['email']);
        $data['user_id'] = User::where('email', $data['email'])->where('type', 'buyer')->value('id');

        $client ??= new Client;
        $client->fill($data)->save();
        Audit::record('quotes', 'client_saved', "Saved client {$client->name}", $client, $actor);
        $this->toast('Client saved.');

        return back();
    }

    public function catalogue(): Response
    {
        return Inertia::render('admin/quotes/catalogue', [
            'items' => CatalogueItem::orderBy('name')->get()->map(fn (CatalogueItem $i): array => $i->only(['id', 'name', 'description', 'unit', 'vat_applicable', 'frequency', 'is_active']) + ['unit_price' => $i->unit_price / 100]),
        ]);
    }

    public function saveItem(Request $request, #[CurrentUser] User $actor, ?CatalogueItem $item = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:500'],
            'unit' => ['required', 'string', 'max:20'], 'unit_price' => ['required', 'numeric', 'min:0'],
            'vat_applicable' => ['boolean'], 'frequency' => ['required', Rule::in(['once', 'monthly', 'annual'])], 'is_active' => ['boolean'],
        ]);
        $data['unit_price'] = QuoteMath::cents($data['unit_price']);

        $item ??= new CatalogueItem;
        $item->fill($data)->save();
        Audit::record('quotes', 'catalogue_saved', "Saved catalogue item {$item->name}", $item, $actor);
        $this->toast('Catalogue item saved.');

        return back();
    }

    public function templates(): Response
    {
        return Inertia::render('admin/quotes/templates', [
            'templates' => QuoteTemplate::orderBy('name')->get()->map(fn (QuoteTemplate $t): array => [
                'id' => $t->id, 'name' => $t->name, 'needsReview' => $t->needs_review, 'lines' => count((array) ($t->content['lines'] ?? [])),
            ]),
        ]);
    }

    public function updateTemplate(Request $request, QuoteTemplate $template, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'needs_review' => ['boolean']]);
        $template->fill($data)->save();
        Audit::record('quotes', 'template_updated', "Updated template {$template->name}", null, $actor);

        return back();
    }

    public function destroyTemplate(QuoteTemplate $template, #[CurrentUser] User $actor): RedirectResponse
    {
        $name = $template->name;
        $template->delete();
        Audit::record('quotes', 'template_deleted', "Deleted template {$name}", null, $actor);

        return back();
    }

    public function settings(QuotationSettings $settings): Response
    {
        return Inertia::render('admin/quotes/settings', ['settings' => $settings->toArray() + ['vat_rate' => $settings->vat_rate_bps / 100]]);
    }

    public function updateSettings(Request $request, QuotationSettings $settings, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:160'], 'registration_number' => ['nullable', 'string', 'max:40'],
            'vat_number' => ['nullable', 'string', 'max:30'], 'address' => ['nullable', 'string', 'max:500'],
            'bank_details' => ['nullable', 'string', 'max:1000'], 'vat_rate' => ['required', 'numeric', 'min:0', 'max:30'],
            'validity_days' => ['required', 'integer', 'min:1', 'max:365'], 'approval_threshold' => ['required', 'integer', 'min:0'],
            'reminder_days' => ['required', 'integer', 'min:0', 'max:60'], 'number_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9-]+$/'],
        ]);

        foreach (['company_name', 'validity_days', 'approval_threshold', 'reminder_days', 'number_prefix'] as $key) {
            $settings->{$key} = $data[$key];
        }
        foreach (['registration_number', 'vat_number', 'address', 'bank_details'] as $key) {
            $settings->{$key} = (string) ($data[$key] ?? '');
        }
        $settings->vat_rate_bps = (int) round(((float) $data['vat_rate']) * 100);
        $settings->save();

        Audit::record('quotes', 'settings_updated', 'Updated quotation settings', null, $actor);
        $this->toast('Quotation settings saved.');

        return back();
    }

    private function toast(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);
    }
}
