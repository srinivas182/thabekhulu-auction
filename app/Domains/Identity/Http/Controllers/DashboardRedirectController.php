<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/**
 * Fortify's "home" after login, sign-up and verification. Sends each
 * account type to its own area.
 */
class DashboardRedirectController extends Controller
{
    public function __invoke(#[CurrentUser] User $user): RedirectResponse
    {
        $query = request()->getQueryString();
        $target = $user->isStaff() ? route('admin.dashboard') : route('account');

        return redirect()->to($query ? $target.'?'.$query : $target);
    }
}
