<?php

namespace App\Domains\Platform\Http\Controllers;

use App\Domains\Platform\Features\Flags;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeatureController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/features/index', ['flags' => Flags::all()]);
    }

    public function update(Request $request, string $flag, #[CurrentUser] User $actor): RedirectResponse
    {
        $request->validate(['active' => ['required', 'boolean']]);

        Flags::set($flag, $request->boolean('active'), $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Feature updated.')]);

        return back();
    }
}
