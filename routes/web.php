<?php

use App\Domains\Access\Http\Controllers\RoleController;
use App\Domains\Audit\Http\Controllers\AuditController;
use App\Domains\Catalogue\Http\Controllers\CategoryController;
use App\Domains\Catalogue\Http\Controllers\LocationController;
use App\Domains\Identity\Http\Controllers\DashboardRedirectController;
use App\Domains\Identity\Http\Controllers\InvitationController;
use App\Domains\Identity\Http\Controllers\StaffController;
use App\Domains\Platform\Http\Controllers\AdminDashboardController;
use App\Domains\Platform\Http\Controllers\FeatureController;
use App\Domains\Settings\Http\Controllers\SettingsController;
use App\Domains\Theme\Http\Controllers\ThemeController;
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
Route::middleware(['auth', 'verified', 'staff', 'staff.2fa', 'admin.ip'])
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

        Route::middleware('can:categories.manage')->group(function () {
            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::get('categories/{category}', [CategoryController::class, 'edit'])->name('categories.edit');
            Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::post('categories/{category}/fields', [CategoryController::class, 'storeField'])->name('categories.fields.store');
            Route::put('categories/{category}/fields/{field}', [CategoryController::class, 'updateField'])->name('categories.fields.update');
            Route::delete('categories/{category}/fields/{field}', [CategoryController::class, 'destroyField'])->name('categories.fields.destroy');
            Route::post('categories/{category}/fields/{field}/move', [CategoryController::class, 'moveField'])->name('categories.fields.move');
            Route::post('categories/{category}/documents', [CategoryController::class, 'storeDocument'])->name('categories.documents.store');
            Route::put('categories/{category}/documents/{document}', [CategoryController::class, 'updateDocument'])->name('categories.documents.update');
            Route::delete('categories/{category}/documents/{document}', [CategoryController::class, 'destroyDocument'])->name('categories.documents.destroy');

            Route::get('locations', [LocationController::class, 'index'])->name('locations.index');
            Route::post('locations/cities', [LocationController::class, 'store'])->name('locations.cities.store');
            Route::post('locations/cities/{city}/toggle', [LocationController::class, 'toggle'])->name('locations.cities.toggle');
        });

        Route::get('settings/{group?}', [SettingsController::class, 'edit'])
            ->whereIn('group', ['general', 'auction', 'security'])
            ->middleware('can:settings.view')->name('settings.edit');
        Route::put('settings/{group}', [SettingsController::class, 'update'])
            ->whereIn('group', ['general', 'auction', 'security'])
            ->middleware('can:settings.manage')->name('settings.update');

        Route::get('audit', [AuditController::class, 'index'])->middleware('can:audit.view')->name('audit.index');
        Route::get('audit/export', [AuditController::class, 'export'])->middleware('can:audit.export')->name('audit.export');

        Route::middleware('can:theme.manage')->prefix('theme/{scope}')->whereIn('scope', ['website', 'admin'])->name('theme.')->group(function () {
            Route::get('/', [ThemeController::class, 'edit'])->name('edit');
            Route::post('draft', [ThemeController::class, 'saveDraft'])->name('draft.save');
            Route::delete('draft', [ThemeController::class, 'discard'])->name('draft.discard');
            Route::post('publish', [ThemeController::class, 'publish'])->name('publish');
            Route::post('reset', [ThemeController::class, 'reset'])->name('reset');
            Route::post('versions/{version}/restore', [ThemeController::class, 'restore'])->name('restore');
        });

        Route::get('features', [FeatureController::class, 'index'])->middleware('can:features.manage')->name('features.index');
        Route::put('features/{flag}', [FeatureController::class, 'update'])->middleware('can:features.manage')->name('features.update');
    });

require __DIR__.'/settings.php';
