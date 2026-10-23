<?php

namespace App\Domains\Payments\Providers;

use App\Domains\Payments\Contracts\PaymentProvider;
use App\Domains\Payments\Data\ConfirmedPayment;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Verification\Data\CheckResult;
use App\Domains\Verification\Enums\Outcome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * TradeSafe escrow (docs.tradesafe.co.za): OAuth client credentials at
 * auth.tradesafe.co.za, GraphQL API. Flow: buyer token → transactionCreate
 * (our deposit reference as theirs, Thabekhulu as the receiving party) →
 * checkoutLink (valid 15 minutes; EFT and Ozow only, cards cap at R25 000).
 *
 * Callbacks are not signed, so a callback is accepted only with the secret
 * in its URL AND from TradeSafe's published IPs, and money is recorded only
 * after the API itself reports FUNDS_RECEIVED for that transaction.
 */
final class TradeSafeProvider implements PaymentProvider
{
    private const AUTH_URL = 'https://auth.tradesafe.co.za/oauth/token';

    private const GRAPHQL = ['sandbox' => 'https://api-developer.tradesafe.dev/graphql', 'live' => 'https://api.tradesafe.co.za/graphql'];

    /** Published callback source addresses. */
    public const CALLBACK_IPS = ['sandbox' => ['13.244.48.16', '13.244.147.116'], 'live' => ['13.244.170.245', '13.244.43.204']];

    public function key(): string
    {
        return 'tradesafe';
    }

    public function name(): string
    {
        return 'TradeSafe escrow';
    }

    public function ready(): bool
    {
        return true;
    }

    public function integrationNote(): string
    {
        return 'Holds deposits in escrow (backed by Standard Bank). In TradeSafe, set the callback URL shown below. Confirm the token-creation input and the live API address in the sandbox before going live.';
    }

    public function supports(): array
    {
        return ['deposit'];
    }

    public function credentialFields(): array
    {
        return [
            ['name' => 'client_id', 'label' => 'Client ID', 'secret' => false],
            ['name' => 'client_secret', 'label' => 'Client secret', 'secret' => true],
            ['name' => 'party_token', 'label' => 'Thabekhulu’s TradeSafe token (receiving party)', 'secret' => false],
            ['name' => 'callback_secret', 'label' => 'Callback secret (any long random text)', 'secret' => true],
            ['name' => 'graphql_url', 'label' => 'API address (leave empty for the default)', 'secret' => false],
        ];
    }

    public function testConnection(array $credentials, string $mode): CheckResult
    {
        try {
            $data = $this->graphql('query { apiProfile { organizations { name } } }', [], $credentials, $mode);
        } catch (Throwable $e) {
            return new CheckResult(Outcome::Failed, ['TradeSafe: '.$e->getMessage()]);
        }

        $org = data_get($data, 'apiProfile.organizations.0.name');

        return new CheckResult(Outcome::Passed, ['Connected to TradeSafe ('.$mode.')'.($org ? " as {$org}" : '').'.']);
    }

    public function checkoutUrl(Deposit $deposit, array $credentials, string $mode, string $returnUrl): string
    {
        $deposit->loadMissing(['user', 'listing']);

        if ($deposit->provider_ref === null) {
            $user = $deposit->user;
            $token = data_get($this->graphql(
                'mutation tokenCreate($input: TokenInput!) { tokenCreate(input: $input) { id } }',
                ['input' => ['user' => ['givenName' => $user->first_name, 'familyName' => $user->last_name, 'email' => $user->email, 'mobile' => (string) $user->mobile]]],
                $credentials, $mode,
            ), 'tokenCreate.id');

            $transaction = $this->graphql(
                'mutation transactionCreate($input: CreateTransactionInput) { transactionCreate(input: $input) { id } }',
                ['input' => [
                    'title' => "Deposit {$deposit->reference}",
                    'description' => "Auction deposit for {$deposit->listing->title}, held under the Rules of Auction.",
                    'industry' => 'GENERAL_GOODS_SERVICES', 'currency' => 'ZAR', 'feeAllocation' => 'SELLER', 'reference' => $deposit->reference,
                    'allocations' => ['create' => [['title' => 'Auction deposit', 'description' => $deposit->reference, 'value' => round($deposit->amount / 100, 2), 'daysToDeliver' => 60, 'daysToInspect' => 7]]],
                    'parties' => ['create' => [['token' => $token, 'role' => 'BUYER'], ['token' => (string) ($credentials['party_token'] ?? ''), 'role' => 'SELLER']]],
                ]],
                $credentials, $mode,
            );
            $deposit->provider_ref = (string) data_get($transaction, 'transactionCreate.id');
            $deposit->method = 'tradesafe';
        }

        $link = data_get($this->graphql(
            'mutation checkoutLink($id: ID!) { checkoutLink(transactionId: $id, paymentMethods: [EFT, OZOW]) }',
            ['id' => $deposit->provider_ref], $credentials, $mode,
        ), 'checkoutLink');

        if (! is_string($link) || ! str_starts_with($link, 'https://')) {
            throw new RuntimeException('TradeSafe did not return a payment link.');
        }

        return $link;
    }

    public function confirm(Request $request, array $credentials, string $mode): ?ConfirmedPayment
    {
        $secret = (string) ($credentials['callback_secret'] ?? '');
        if ($secret === '' || ! hash_equals($secret, (string) $request->route('secret'))) {
            return null;
        }
        if (! in_array($request->ip(), self::CALLBACK_IPS[$mode === 'live' ? 'live' : 'sandbox'], true)) {
            Log::warning('TradeSafe callback from an unexpected address', ['ip' => $request->ip()]);

            return null;
        }

        $id = (string) data_get($request->all(), 'data.id', '');
        if ($id === '') {
            return null;
        }

        // Never trust the callback body: ask TradeSafe for the real state and amount.
        $tx = data_get($this->graphql('query transaction($id: ID!) { transaction(id: $id) { id reference state allocations { value } } }', ['id' => $id], $credentials, $mode), 'transaction');
        if (! is_array($tx) || ($tx['state'] ?? null) !== 'FUNDS_RECEIVED') {
            return null;
        }

        $cents = (int) round(collect((array) ($tx['allocations'] ?? []))->sum(fn ($a) => (float) ($a['value'] ?? 0)) * 100);

        return new ConfirmedPayment((string) $tx['reference'], $cents, (string) $tx['id'], $tx['id'].':FUNDS_RECEIVED');
    }

    /** transactionCancel refunds the buyer when a deposit was made (state REFUNDED). */
    public function refund(Deposit $deposit, array $credentials, string $mode, string $comment): string
    {
        $state = data_get($this->graphql(
            'mutation transactionCancel($id: ID!, $comment: String) { transactionCancel(id: $id, comment: $comment) { state } }',
            ['id' => (string) $deposit->provider_ref, 'comment' => $comment], $credentials, $mode,
        ), 'transactionCancel.state');

        if ($state !== 'REFUNDED') {
            throw new RuntimeException("TradeSafe did not refund the deposit (state: {$state}).");
        }

        return (string) $deposit->provider_ref;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @param  array<string, string>  $credentials
     * @return array<string, mixed>
     */
    private function graphql(string $query, array $variables, array $credentials, string $mode): array
    {
        $url = ($credentials['graphql_url'] ?? '') !== '' ? (string) $credentials['graphql_url'] : self::GRAPHQL[$mode === 'live' ? 'live' : 'sandbox'];
        $response = Http::timeout(25)->acceptJson()->withToken($this->accessToken($credentials, $mode))->post($url, ['query' => $query, 'variables' => (object) $variables]);

        if (! $response->successful() || $response->json('errors')) {
            throw new RuntimeException((string) ($response->json('errors.0.message') ?? 'HTTP '.$response->status()));
        }

        return (array) $response->json('data');
    }

    /** @param array<string, string> $credentials */
    private function accessToken(array $credentials, string $mode): string
    {
        $key = 'tradesafe-token:'.$mode.':'.hash('sha256', ($credentials['client_id'] ?? '').($credentials['client_secret'] ?? ''));

        return (string) Cache::remember($key, now()->addMinutes(50), function () use ($credentials) {
            $response = Http::asForm()->timeout(20)->post(self::AUTH_URL, [
                'grant_type' => 'client_credentials', 'client_id' => $credentials['client_id'] ?? '', 'client_secret' => $credentials['client_secret'] ?? '',
            ]);
            if (! $response->successful() || ! is_string($response->json('access_token'))) {
                throw new RuntimeException('TradeSafe refused the client ID or secret.');
            }

            return $response->json('access_token');
        });
    }
}
