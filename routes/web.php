<?php

use App\Domains\Platform\Http\Controllers\AdminDashboardController;
use Illuminate\Support\Facades\Route;

/*
| Public website
*/
Route::inertia('/', 'public/home')->name('home');

/*
| Back office. Sprint 2b adds staff-only access and role permissions.
*/
Route::middleware(['auth', 'verified'])->prefix('admin')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
});

require __DIR__.'/settings.php';
