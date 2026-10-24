<?php

namespace App\Domains\Platform\Http\Middleware;

use App\Domains\Platform\Features\Flags;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route guard: `feature:sellers` answers 404 while the flag is off, so the pages simply don't exist. */
class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $flag): Response
    {
        abort_unless(Flags::active($flag), 404);

        return $next($request);
    }
}
