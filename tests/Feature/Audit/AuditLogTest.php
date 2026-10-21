<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Audit\Models\AuditEntry;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    $this->admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
});

it('records sign-ins with the IP address', function () {
    $buyer = User::factory()->create();

    $this->post(route('login.store'), ['email' => $buyer->email, 'password' => 'password'], ['REMOTE_ADDR' => '196.25.1.9']);

    $entry = AuditEntry::where('log_name', 'auth')->where('event', 'login')->firstOrFail();
    expect($entry->causer_id)->toBe($buyer->id)
        ->and($entry->ip_address)->toBe('196.25.1.9');
});

it('records failed sign-ins', function () {
    $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'wrong-password']);

    $entry = AuditEntry::where('event', 'login_failed')->firstOrFail();
    expect($entry->properties['email'])->toBe('nobody@example.com');
});

it('records staff lifecycle events from 2b', function () {
    $staff = User::factory()->staff(SystemRole::Support)->create();

    $this->actingAs($this->admin)->post(route('admin.staff.deactivate', $staff));
    $this->actingAs($this->admin)->put(route('admin.staff.role', $staff), ['role' => 'finance']);

    expect(AuditEntry::where('log_name', 'staff')->pluck('event')->all())->toContain('deactivated', 'role_changed');
});

it('records role permission changes with before and after', function () {
    $role = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
    $role->forceFill(['label' => 'Viewer'])->save();
    $role->givePermissionTo('dashboard.view');

    $this->actingAs($this->admin)->put(route('admin.roles.update', $role), [
        'label' => 'Viewer',
        'permissions' => ['dashboard.view', 'reports.view'],
    ]);

    $entry = AuditEntry::where('log_name', 'roles')->where('event', 'updated')->firstOrFail();
    expect($entry->properties['old']['permissions'])->toBe(['dashboard.view'])
        ->and($entry->properties['attributes']['permissions'])->toBe(['dashboard.view', 'reports.view']);
});

it('masks mobile numbers and never stores passwords', function () {
    $user = User::factory()->create(['mobile' => '+27821234567']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'first_name' => $user->first_name, 'last_name' => $user->last_name,
        'email' => $user->email, 'mobile' => '0839876543',
    ]);
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'NewThabekhulu2026',
            'password_confirmation' => 'NewThabekhulu2026',
        ]);

    $profile = AuditEntry::where('log_name', 'users')->where('event', 'updated')->latest('id')->firstOrFail();
    // +27 numbers are 12 characters: 8 masked, last 4 shown.
    expect($profile->properties['attributes']['mobile'])->toBe(str_repeat('•', 8).'6543')
        ->and($profile->properties['old']['mobile'])->toBe(str_repeat('•', 8).'4567');

    $everything = AuditEntry::all()->map(fn ($e) => json_encode($e->properties))->implode(' ');
    expect($everything)->not->toContain('NewThabekhulu2026')
        ->and(AuditEntry::where('event', 'password_changed')->exists())->toBeTrue();
});

it('cannot be edited or deleted', function () {
    $this->actingAs($this->admin)->post(route('logout'));
    $entry = AuditEntry::firstOrFail();

    expect(fn () => $entry->update(['description' => 'tampered']))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);
});

it('filters entries by module, person and date', function () {
    $this->actingAs($this->admin)->post(route('admin.staff.invite'), ['email' => 'a@example.com', 'role' => 'finance']);

    $this->actingAs($this->admin)->get(route('admin.audit.index', ['module' => 'staff']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/audit/index')
            ->where('entries.data', fn ($data) => collect($data)->every(fn ($e) => $e['module'] === 'staff') && count($data) > 0));

    $this->actingAs($this->admin)->get(route('admin.audit.index', ['user' => 'no-such-person']))
        ->assertInertia(fn (Assert $page) => $page->where('entries.total', 0));

    $this->actingAs($this->admin)->get(route('admin.audit.index', ['from' => now()->addDay()->format('Y-m-d')]))
        ->assertInertia(fn (Assert $page) => $page->where('entries.total', 0));
});

it('exports to CSV and records the export', function () {
    $this->actingAs($this->admin)->post(route('admin.staff.invite'), ['email' => 'a@example.com', 'role' => 'finance']);

    $response = $this->actingAs($this->admin)->get(route('admin.audit.export', ['module' => 'staff']));

    $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();
    expect($csv)->toContain('"Time (SAST)",Module,Event')->toContain('invited');
    expect(AuditEntry::where('log_name', 'audit')->where('event', 'exported')->exists())->toBeTrue();
});

it('requires audit permissions', function () {
    $support = User::factory()->staff(SystemRole::Support)->create();
    $this->actingAs($support)->get(route('admin.audit.index'))->assertForbidden();

    Role::create(['name' => 'auditor', 'guard_name' => 'web'])->givePermissionTo(['dashboard.view', 'audit.view']);
    $auditor = User::factory()->staff('auditor')->create();
    $this->actingAs($auditor)->get(route('admin.audit.index'))->assertOk();
    $this->actingAs($auditor)->get(route('admin.audit.export'))->assertForbidden();
});
