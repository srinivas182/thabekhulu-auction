<?php

use App\Domains\Access\Http\Controllers\RoleController;
use App\Domains\Identity\Http\Controllers\DashboardRedirectController;
use App\Domains\Identity\Http\Controllers\InvitationController;
use App\Domains\Identity\Http\Controllers\StaffController;
use App\Domains\Platform\Http\Controllers\AdminDashboardController;
use Illuminate\Support\Facades\Route;

/*
| Public website
*/
Route::inertia('/', 'public/home')->name('home');

/*
| Staff invitations (signed-out users accept their invite here)
*/
Route::middleware(['guest', 'throttle:invitations'])->group(function () {
    Route::get('invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}', [InvitationController::class, 'store'])->name('invitations.accept');
});

/*
| Signed-in home: routes each account type to its own area
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardRedirectController::class)->name('dashboard');
    // Buyer area placeholder; Sprint 4 builds the buyer dashboard.
    Route::inertia('account', 'account/dashboard')->name('account');
});

/*
| Back office: staff only, two-factor required, every page permission-checked
*/
Route::middleware(['auth', 'verified', 'staff', 'staff.2fa'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', AdminDashboardController::class)->middleware('can:dashboard.view')->name('dashboard');

        Route::get('staff', [StaffController::class, 'index'])->middleware('can:staff.view')->name('staff.index');
        Route::post('staff/invitations', [StaffController::class, 'invite'])->middleware('can:staff.invite')->name('staff.invite');
        Route::delete('staff/invitations/{invitation}', [StaffController::class, 'revokeInvitation'])->middleware('can:staff.invite')->name('staff.invitations.revoke');
        Route::put('staff/{user}/role', [StaffController::class, 'changeRole'])->middleware('can:staff.invite')->name('staff.role');
        Route::post('staff/{user}/deactivate', [StaffController::class, 'deactivate'])->middleware('can:staff.deactivate')->name('staff.deactivate');
        Route::post('staff/{user}/reactivate', [StaffController::class, 'reactivate'])->middleware('can:staff.deactivate')->name('staff.reactivate');

        Route::get('roles', [RoleController::class, 'index'])->middleware('can:roles.view')->name('roles.index');
        Route::middleware('can:roles.manage')->group(function () {
            Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
            Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
            Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
            Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        });
    });

require __DIR__.'/settings.php';
