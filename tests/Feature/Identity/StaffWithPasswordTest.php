<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Security\ApprovalGate;
use App\Domains\Settings\Settings\SecuritySettings;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    $this->admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
});

function createStaff(User $by, array $data = []): ?string
{
    test()->actingAs($by)->post(route('admin.staff.create'), array_merge(['first_name' => 'Thandi', 'last_name' => 'Nkosi', 'email' => 'thandi@example.com', 'role' => 'finance'], $data));

    return session('inertia.flash_data')['temporaryPassword']['password'] ?? null;
}

it('creates a staff account with a one-time temporary password', function () {
    $password = createStaff($this->admin);

    $user = User::where('email', 'thandi@example.com')->firstOrFail();
    expect($password)->toBeString()->toHaveLength(16)
        ->and($user->type->value)->toBe('staff')
        ->and($user->hasRole('finance'))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check($password, $user->password))->toBeTrue();
});

it('makes the new person choose their own password before using the back office', function () {
    $password = createStaff($this->admin);
    $user = User::where('email', 'thandi@example.com')->firstOrFail();

    $this->actingAs($user)->get(route('admin.dashboard'))->assertRedirect(route('security.edit'));
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
        ->put(route('user-password.update'), ['current_password' => $password, 'password' => 'A-new-strong-pass-2026', 'password_confirmation' => 'A-new-strong-pass-2026'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->must_change_password)->toBeFalse();
    // With staff two-factor switched off, the back office now opens (two-factor setup is tested elsewhere).
    $settings = app(SecuritySettings::class);
    $settings->staff_two_factor_required = false;
    $settings->save();
    $this->actingAs($user->fresh())->get(route('admin.dashboard'))->assertOk();
});

it('lets only super admins create super admins, and refuses staff without permission or a used email', function () {
    $staffAdmin = User::factory()->staff(SystemRole::Support)->create();
    $staffAdmin->syncRoles([]);
    $staffAdmin->givePermissionTo(['staff.view', 'staff.invite']);
    $this->actingAs($staffAdmin)->post(route('admin.staff.create'), ['first_name' => 'A', 'last_name' => 'B', 'email' => 'boss@example.com', 'role' => 'super-admin'])->assertForbidden();

    $finance = User::factory()->staff(SystemRole::Finance)->create();
    $this->actingAs($finance)->post(route('admin.staff.create'), ['first_name' => 'A', 'last_name' => 'B', 'email' => 'x@example.com', 'role' => 'finance'])->assertForbidden();

    createStaff($this->admin);
    $this->actingAs($this->admin)->post(route('admin.staff.create'), ['first_name' => 'A', 'last_name' => 'B', 'email' => 'thandi@example.com', 'role' => 'finance'])->assertSessionHasErrors('email');
});

it('issues a new temporary password, signing the person out, never for yourself or (unless super admin) a super admin', function () {
    createStaff($this->admin);
    $user = User::where('email', 'thandi@example.com')->firstOrFail();
    $user->forceFill(['must_change_password' => false])->save();
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    $oldHash = $user->password;

    $this->actingAs($this->admin)->post(route('admin.staff.temporary-password', $user->ulid))->assertSessionHasNoErrors();
    $password = session('inertia.flash_data')['temporaryPassword']['password'] ?? null;
    $user->refresh();
    expect($password)->toBeString()->toHaveLength(16)
        ->and($user->password)->not->toBe($oldHash)->and(Hash::check($password, $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('activity_log')->where('event', 'temporary_password_issued')->where('subject_id', $user->id)->exists())->toBeTrue();

    $this->actingAs($this->admin)->post(route('admin.staff.temporary-password', $this->admin->ulid))->assertSessionHasErrors('staff');
    $staffAdmin = User::factory()->staff(SystemRole::Support)->create();
    $staffAdmin->syncRoles([]);
    $staffAdmin->givePermissionTo(['staff.view', 'staff.invite']);
    $this->actingAs($staffAdmin)->post(route('admin.staff.temporary-password', $this->admin->ulid))->assertForbidden();

    $route = app('router')->getRoutes()->getByName('admin.staff.temporary-password');
    $request = Request::create('/'.$route->uri(), 'POST');
    $request->setRouteResolver(fn () => $route->bind($request));
    expect(ApprovalGate::label($request))->toBeNull(); // no security code needed (audit-logged instead)
});
