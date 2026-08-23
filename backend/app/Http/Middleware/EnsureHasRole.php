<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Centralized role check (CLAUDE.md §8: "Role checks are centralized
 * (middleware/guard pattern), not scattered ad hoc checks"). Usage:
 * ->middleware('role:manager,super_admin') - any listed role passes.
 */
class EnsureHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyRole($roles)) {
            abort(403, 'You do not have the required role for this action.');
        }

        return $next($request);
    }
}
