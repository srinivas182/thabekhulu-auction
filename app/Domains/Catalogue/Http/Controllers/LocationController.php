<?php

namespace App\Domains\Catalogue\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Catalogue\Models\City;
use App\Domains\Catalogue\Models\Province;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * South African provinces (fixed) and the cities listings can be placed
 * in. Cities can be added or hidden, not deleted, so listings keep them.
 */
class LocationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/locations/index', [
            'provinces' => Province::with('cities')->orderBy('name')->get()->map(fn (Province $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'cities' => $p->cities->map(fn (City $c): array => ['id' => $c->id, 'name' => $c->name, 'active' => $c->is_active]),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'province_id' => ['required', 'integer', 'exists:provinces,id'],
            'name' => ['required', 'string', 'max:80'],
        ]);
        $slug = Str::slug($data['name']);

        if (City::where('province_id', $data['province_id'])->where('slug', $slug)->exists()) {
            return back()->withErrors(['name' => __('That city is already listed in this province.')]);
        }

        $city = City::create(['province_id' => $data['province_id'], 'name' => $data['name'], 'slug' => $slug]);

        Audit::record('catalogue', 'city_created', "Added city {$city->name}", $city);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('City added.')]);

        return back();
    }

    public function toggle(City $city): RedirectResponse
    {
        $city->forceFill(['is_active' => ! $city->is_active])->save();

        Audit::record('catalogue', $city->is_active ? 'city_shown' : 'city_hidden',
            ($city->is_active ? 'Showed city ' : 'Hid city ').$city->name, $city);

        return back();
    }
}
