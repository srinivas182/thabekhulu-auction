<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Audit\Models\AuditEntry;
use App\Domains\Platform\Features\Flags;
use App\Models\User;
use Database\Seeders\AccessSeeder;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    $this->admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
});

it('starts with every Phase 2 feature switched off', function () {
    expect(Flags::active('sellers'))->toBeFalse()
        ->and(Flags::active('consortium_bidding'))->toBeFalse()
        ->and(Flags::active('investor_club'))->toBeFalse()
        ->and(Flags::active('whatsapp_notifications'))->toBeFalse();
});

it('switches a feature on and off and records it', function () {
    $this->actingAs($this->admin)->put(route('admin.features.update', 'sellers'), ['active' => true])->assertSessionHasNoErrors();
    expect(Flags::active('sellers'))->toBeTrue();

    $this->actingAs($this->admin)->put(route('admin.features.update', 'sellers'), ['active' => false]);
    expect(Flags::active('sellers'))->toBeFalse();

    expect(AuditEntry::where('log_name', 'features')->pluck('event')->all())->toBe(['enabled', 'disabled']);
});

it('controls search engine indexing', function () {
    $this->get(route('home'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    Flags::set('public_site_indexing', true);

    $this->get(route('home'))->assertHeaderMissing('X-Robots-Tag');
});

it('rejects unknown flags and staff without permission', function () {
    $this->actingAs($this->admin)->put(route('admin.features.update', 'not-a-flag'), ['active' => true])->assertNotFound();

    $support = User::factory()->staff(SystemRole::Support)->create();
    $this->actingAs($support)->get(route('admin.features.index'))->assertForbidden();
    $this->actingAs($support)->put(route('admin.features.update', 'sellers'), ['active' => true])->assertForbidden();
});
