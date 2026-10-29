<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Payments\Settings\PaymentSettings;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    $this->admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
});

function trust(array $overrides = []): array
{
    return array_merge(['trust_bank' => 'FNB', 'trust_account_name' => 'Thabekhulu Trust Account', 'trust_account_number' => '62 1234 5678',
        'trust_branch_code' => '250 655', 'refund_business_days' => 5, 'runner_up_hold_days' => 30], $overrides);
}

it('saves trust account numbers typed with spaces, and shows buyers the details', function () {
    $this->actingAs($this->admin)->get(route('admin.finance.settings'))->assertInertia(fn (Assert $p) => $p->where('visibleToBuyers', false)->has('banks'));

    $this->actingAs($this->admin)->put(route('admin.finance.settings.update'), trust())->assertSessionHasNoErrors();
    $s = app(PaymentSettings::class)->refresh();
    expect($s->trust_account_number)->toBe('6212345678')->and($s->trust_branch_code)->toBe('250655')->and($s->trustAccountReady())->toBeTrue();

    $this->actingAs($this->admin)->get(route('admin.finance.settings'))->assertInertia(fn (Assert $p) => $p->where('visibleToBuyers', true));
    $this->actingAs($this->admin)->get(route('admin.finance.index'))->assertInertia(fn (Assert $p) => $p->where('trustAccountReady', true));
});

it('requires the bank, account number and branch code together, with clear messages', function () {
    $this->actingAs($this->admin)->put(route('admin.finance.settings.update'), trust(['trust_account_number' => '']))->assertSessionHasErrors('trust_account_number');
    $this->actingAs($this->admin)->put(route('admin.finance.settings.update'), trust(['trust_account_number' => 'abc123']))->assertSessionHasErrors('trust_account_number');
    $this->actingAs($this->admin)->put(route('admin.finance.settings.update'), trust(['trust_branch_code' => '1234']))->assertSessionHasErrors('trust_branch_code');
    expect(app(PaymentSettings::class)->refresh()->trustAccountReady())->toBeFalse();
});
