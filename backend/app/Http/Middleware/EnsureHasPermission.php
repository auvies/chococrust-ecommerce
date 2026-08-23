<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Centralized permission check (CLAUDE.md §8). Usage:
 * ->middleware('permission:orders.manage') - any listed permission passes.
 */
class EnsureHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyPermission($permissions)) {
            abort(403, 'You do not have the required permission for this action.');
        }

        return $next($request);
    }
}
