<?php

namespace App\Domains\Platform\Http\Controllers;

use App\Domains\Platform\Health\HealthCheck;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(HealthCheck $health): Response
    {
        return Inertia::render('admin/dashboard', [
            'health' => $health->run(),
        ]);
    }
}
