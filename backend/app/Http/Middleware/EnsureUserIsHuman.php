<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense in depth for "AI agents must NOT be normal administrators":
 * applied to every admin route regardless of role/permission checks, so an
 * ai_agent-type identity is structurally blocked from the admin surface
 * even if it were ever mistakenly granted an overlapping permission.
 */
class EnsureUserIsHuman
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isHuman()) {
            abort(403, 'This area is restricted to human staff accounts.');
        }

        return $next($request);
    }
}
