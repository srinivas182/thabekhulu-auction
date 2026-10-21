<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Identity\Events\StaffDeactivated;
use App\Domains\Identity\Events\StaffRoleChanged;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    $this->admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
});

it('deactivates staff and removes their stored sessions', function () {
    Event::fake([StaffDeactivated::class]);
    $staff = User::factory()->staff(SystemRole::Support)->create();
    DB::table('sessions')->insert([
        'id' => 'session-1', 'user_id' => $staff->id, 'ip_address' => '127.0.0.1',
        'user_agent' => 'test', 'payload' => '', 'last_activity' => time(),
    ]);

    $this->actingAs($this->admin)->post(route('admin.staff.deactivate', $staff))->assertSessionHasNoErrors();

    expect($staff->refresh()->isActive())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $staff->id)->count())->toBe(0);
    Event::assertDispatched(StaffDeactivated::class);
});

it('reactivates staff', function () {
    $staff = User::factory()->staff(SystemRole::Support)->deactivated()->create();

    $this->actingAs($this->admin)->post(route('admin.staff.reactivate', $staff));

    expect($staff->refresh()->isActive())->toBeTrue();
});

it('does not let staff deactivate themselves', function () {
    User::factory()->staff(SystemRole::SuperAdmin)->create();

    $this->actingAs($this->admin)->post(route('admin.staff.deactivate', $this->admin))
        ->assertSessionHasErrors('staff');
});

it('always keeps one active super admin', function () {
    $other = User::factory()->staff(SystemRole::SuperAdmin)->create();
    $this->actingAs($this->admin)->post(route('admin.staff.deactivate', $other))->assertSessionHasNoErrors();

    // $this->admin is now the only active super admin; a second admin can't demote them.
    $deputy = User::factory()->staff(SystemRole::Auctioneer)->create();
    $deputy->givePermissionTo('staff.invite');

    $this->actingAs($deputy)->put(route('admin.staff.role', $this->admin), ['role' => 'support'])
        ->assertSessionHasErrors('staff');
    expect($this->admin->refresh()->isSuperAdmin())->toBeTrue();
});

it('changes a staff member’s role', function () {
    Event::fake([StaffRoleChanged::class]);
    $staff = User::factory()->staff(SystemRole::Support)->create();

    $this->actingAs($this->admin)->put(route('admin.staff.role', $staff), ['role' => 'finance'])
        ->assertSessionHasNoErrors();

    expect($staff->refresh()->getRoleNames()->all())->toBe(['finance']);
    Event::assertDispatched(StaffRoleChanged::class, fn ($e) => $e->from === 'support' && $e->to === 'finance');
});

it('only manages staff accounts, not buyers', function () {
    $buyer = User::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.staff.deactivate', $buyer))->assertNotFound();
});

it('requires the matching permission for each action', function () {
    $support = User::factory()->staff(SystemRole::Support)->create();
    $staff = User::factory()->staff(SystemRole::Finance)->create();

    $this->actingAs($support)->post(route('admin.staff.deactivate', $staff))->assertForbidden();
    $this->actingAs($support)->post(route('admin.staff.invite'), ['email' => 'a@b.co', 'role' => 'support'])->assertForbidden();
});
