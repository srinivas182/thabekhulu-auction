<?php

namespace App\Http\Middleware;

use App\Domains\Security\ApprovalGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sensitive back-office actions need a code emailed to the security approvers (see ApprovalGate). */
class RequireSecurityApproval
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user !== null && ! $request->isMethod('GET') && ApprovalGate::enabled()) {
            $label = ApprovalGate::label($request);
            if ($label !== null) {
                ApprovalGate::check($request, $user, $label);
            }
        }

        return $next($request);
    }
}
