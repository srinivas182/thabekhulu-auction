<?php

use App\Domains\Access\Enums\SystemRole;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

/*
 * Sprint 9a: every back-office route is protected, now and as the code grows.
 * Exceptions must be listed here with the reason, and are tested below.
 */
const PERMISSION_CHECKED_IN_CONTROLLER = [
    // Viewable by any of settlement.manage, auctions.settle, settlement.confirm_default (SettlementController::mayView).
    'admin.settlement.index', 'admin.settlement.show', 'admin.settlement.document',
];

/** @return list<Route> */
function adminRoutes(): array
{
    return array_values(array_filter(Router::getRoutes()->getRoutes(), fn (Route $r) => str_starts_with($r->uri(), 'admin')));
}

it('guards every back-office route with sign-in, staff, two-factor and a permission', function () {
    $routes = adminRoutes();
    expect(count($routes))->toBeGreaterThan(150);

    foreach ($routes as $r) {
        $mw = $r->gatherMiddleware();
        foreach (['auth', 'staff', 'staff.2fa', 'admin.ip'] as $required) {
            expect(in_array($required, $mw, true))->toBeTrue("{$r->uri()} is missing {$required}");
        }
        $hasPermission = collect($mw)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'can:'));
        expect($hasPermission || in_array($r->getName(), PERMISSION_CHECKED_IN_CONTROLLER, true))
            ->toBeTrue("{$r->uri()} has no permission check; add can: or document it in PERMISSION_CHECKED_IN_CONTROLLER");
    }
});

it('refuses a staff member with no permissions on every back-office page', function () {
    $this->seed(AccessSeeder::class);
    $nobody = User::factory()->staff(SystemRole::Support)->create();
    $nobody->syncRoles([]);
    $nobody->syncPermissions([]);

    $checked = 0;
    foreach (adminRoutes() as $r) {
        if (! in_array('GET', $r->methods(), true) || str_contains($r->uri(), '{')) {
            continue; // parameterised routes are covered by the structure test above
        }
        $status = $this->actingAs($nobody)->get('/'.$r->uri())->getStatusCode();
        expect(in_array($status, [403, 404], true))->toBeTrue("/{$r->uri()} answered {$status} to a staff member with no permissions");
        $checked++;
    }
    expect($checked)->toBeGreaterThan(40);

    $this->actingAs($nobody)->get(route('admin.settlement.index'))->assertForbidden();
});

it('keeps buyers and guests out of the back office', function () {
    $this->seed(AccessSeeder::class);
    $buyer = User::factory()->create(['type' => 'buyer']);

    $this->get('/admin/listings')->assertRedirect();
    $this->actingAs($buyer)->get('/admin/listings')->assertForbidden();
});
