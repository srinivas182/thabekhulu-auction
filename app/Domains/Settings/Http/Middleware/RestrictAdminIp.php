<?php

namespace App\Domains\Settings\Http\Middleware;

use App\Domains\Settings\Settings\SecuritySettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limits the back office to the IP allow-list in security settings.
 * An empty list allows any address.
 */
class RestrictAdminIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = app(SecuritySettings::class)->admin_ip_allowlist;

        abort_if($allowed !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowed), 403,
            __('The back office isn’t available from this network.'));

        return $next($request);
    }
}
