<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Audit\Models\AuditEntry;
use App\Domains\Settings\Settings\AuctionSettings;
use App\Domains\Settings\Settings\GeneralSettings;
use App\Domains\Settings\Settings\SecuritySettings;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    $this->admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
});

function auctionInput(array $overrides = []): array
{
    return array_merge(app(AuctionSettings::class)->toArray(), $overrides);
}

it('starts with the agreed auction defaults', function () {
    $auction = app(AuctionSettings::class);

    expect($auction->default_duration_days)->toBe(30)
        ->and($auction->soft_close_enabled)->toBeTrue()
        ->and($auction->soft_close_window_minutes)->toBe(5)
        ->and($auction->soft_close_extension_minutes)->toBe(5)
        ->and($auction->closing_soon_hours)->toBe(72)
        ->and($auction->runner_up_hold_days)->toBe(14)
        ->and($auction->default_suspension_months)->toBe(12)
        ->and(app(GeneralSettings::class)->currency)->toBe('ZAR')
        ->and(app(SecuritySettings::class)->staff_two_factor_required)->toBeTrue();
});

it('shows each settings section', function (string $group) {
    $this->actingAs($this->admin)->get(route('admin.settings.edit', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/settings/edit')->where('group', $group));
})->with(['general', 'auction', 'security']);

it('saves auction settings and records only what changed', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'auction'), auctionInput(['default_duration_days' => 21, 'soft_close_enabled' => '0']))
        ->assertSessionHasNoErrors();

    $auction = app(AuctionSettings::class)->refresh();
    expect($auction->default_duration_days)->toBe(21)
        ->and($auction->soft_close_enabled)->toBeFalse();

    $entry = AuditEntry::where('log_name', 'settings')->latest('id')->firstOrFail();
    expect($entry->properties['old'])->toBe(['default_duration_days' => 30, 'soft_close_enabled' => true])
        ->and($entry->properties['attributes'])->toBe(['default_duration_days' => 21, 'soft_close_enabled' => false])
        ->and($entry->causer_id)->toBe($this->admin->id);
});

it('validates settings ranges', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'auction'), auctionInput(['default_duration_days' => 0, 'soft_close_window_minutes' => 999]))
        ->assertSessionHasErrors(['default_duration_days', 'soft_close_window_minutes']);
});

it('never changes read-only settings', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.update', 'general'), [
        ...app(GeneralSettings::class)->toArray(),
        'site_name' => 'Thabekhulu Auctions',
        'currency' => 'USD',
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->put(route('admin.settings.update', 'security'), [
        'staff_two_factor_required' => '0',
        'session_lifetime_minutes' => 60,
        'admin_ip_allowlist' => '',
    ])->assertSessionHasNoErrors();

    expect(app(GeneralSettings::class)->refresh()->currency)->toBe('ZAR')
        ->and(app(GeneralSettings::class)->site_name)->toBe('Thabekhulu Auctions')
        ->and(app(SecuritySettings::class)->refresh()->staff_two_factor_required)->toBeTrue()
        ->and(app(SecuritySettings::class)->session_lifetime_minutes)->toBe(60);
});

it('needs settings.view to see and settings.manage to change', function () {
    $support = User::factory()->staff(SystemRole::Support)->create();
    $this->actingAs($support)->get(route('admin.settings.edit'))->assertForbidden();

    Role::create(['name' => 'settings-viewer', 'guard_name' => 'web'])->givePermissionTo(['dashboard.view', 'settings.view']);
    $viewer = User::factory()->staff('settings-viewer')->create();

    $this->actingAs($viewer)->get(route('admin.settings.edit'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false));
    $this->actingAs($viewer)->put(route('admin.settings.update', 'auction'), auctionInput())->assertForbidden();
});

it('refuses an IP allow-list that would lock out the admin', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.update', 'security'), [
        'session_lifetime_minutes' => 120,
        'admin_ip_allowlist' => '196.25.10.0/24',
    ], ['REMOTE_ADDR' => '41.0.0.5'])->assertSessionHasErrors('admin_ip_allowlist');

    $this->actingAs($this->admin)->put(route('admin.settings.update', 'security'), [
        'session_lifetime_minutes' => 120,
        'admin_ip_allowlist' => 'not-an-ip',
    ])->assertSessionHasErrors('admin_ip_allowlist');
});

it('limits the back office to the IP allow-list', function () {
    $security = app(SecuritySettings::class);
    $security->admin_ip_allowlist = ['196.25.10.0/24'];
    $security->save();

    $this->actingAs($this->admin)->get(route('admin.dashboard'), ['REMOTE_ADDR' => '196.25.10.40'])->assertOk();
    $this->actingAs($this->admin)->withServerVariables(['REMOTE_ADDR' => '41.0.0.5'])->get(route('admin.dashboard'))->assertForbidden();
});

it('applies the inactivity timeout from settings', function () {
    $security = app(SecuritySettings::class);
    $security->session_lifetime_minutes = 45;
    $security->save();

    $this->get(route('home'));

    expect(config('session.lifetime'))->toBe(45);
});
