<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * CLAUDE.md §13: idempotency for payment-affecting and order-mutating
 * endpoints where retries are possible. Opt-in per route
 * (->middleware('idempotent')) - once attached, the Idempotency-Key
 * header becomes required. A retried request with the same key/user/route
 * replays the first response instead of re-running the side effect
 * (placing a second order, issuing a second refund, ...).
 */
class EnsureIdempotent
{
    private const TTL_HOURS = 24;

    /** Sentinel: a claimed-but-not-yet-completed row. Never a real HTTP status. */
    private const CLAIMED_STATUS = 0;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '') {
            abort(400, 'This request requires an Idempotency-Key header.');
        }

        $route = $request->route()?->getName() ?? $request->path();
        $userId = $request->user()?->id;

        $existing = IdempotencyKey::where('user_id', $userId)
            ->where('route', $route)
            ->where('key', $key)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return $this->replayOrConflict($existing);
        }

        // Security fix (SECURITY_AUDIT.md): the previous version checked for
        // an existing key, then only wrote the response *after* $next()
        // finished - two requests carrying the identical key sent close
        // together could both miss the check above and both execute the
        // real side effect (double refund, double COD collection), only
        // racing on which write "won" afterward. Claiming the key with an
        // INSERT first, guarded by the table's unique(user_id, route, key)
        // constraint, makes the second request fail this insert instead of
        // ever reaching $next() - the DB constraint is the actual race
        // guard, not the earlier SELECT (which is only a fast path).
        try {
            $claim = IdempotencyKey::create([
                'user_id' => $userId,
                'route' => $route,
                'key' => $key,
                'response_status' => self::CLAIMED_STATUS,
                'response_body' => [],
                'expires_at' => now()->addHours(self::TTL_HOURS),
            ]);
        } catch (QueryException $e) {
            // Lost the race - re-read whichever row won the insert. If none
            // exists, this wasn't actually a duplicate-key collision at all
            // (a genuine, unrelated DB error), so it's rethrown rather than
            // silently swallowed.
            $winner = IdempotencyKey::where('user_id', $userId)->where('route', $route)->where('key', $key)->first();

            if (! $winner) {
                throw $e;
            }

            return $this->replayOrConflict($winner);
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            // The side effect never completed - release the claim so a
            // legitimate retry isn't permanently stuck behind a stale
            // "still processing" row for the next 24 hours.
            $claim->delete();

            throw $e;
        }

        // Only cache successful/expected outcomes - a transient server
        // error shouldn't permanently block a legitimate retry.
        if ($response->getStatusCode() < 500) {
            $claim->update([
                'response_status' => $response->getStatusCode(),
                'response_body' => json_decode($response->getContent(), true) ?? [],
            ]);
        } else {
            $claim->delete();
        }

        return $response;
    }

    private function replayOrConflict(IdempotencyKey $record): Response
    {
        if ($record->response_status === self::CLAIMED_STATUS) {
            abort(409, 'A request with this Idempotency-Key is already being processed. Retry shortly.');
        }

        return response()->json($record->response_body, $record->response_status)
            ->header('Idempotency-Replayed', 'true');
    }
}
