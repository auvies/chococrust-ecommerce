<?php

use App\Http\Middleware\EnsureHasPermission;
use App\Http\Middleware\EnsureHasRole;
use App\Http\Middleware\EnsureIdempotent;
use App\Http\Middleware\EnsureUserIsHuman;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyCsrfCookie;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Every response, API or not (CLAUDE.md's security-headers requirement).
        $middleware->append(SecurityHeaders::class);

        // Laravel's default 'api' group is stateless and doesn't queue
        // cookies onto the response by default. This API IS cookie-based
        // (CLAUDE.md §7), so the queueing middleware has to be added back
        // explicitly. Deliberately NOT adding EncryptCookies: the access
        // token is a self-verifying signed JWT and the refresh/CSRF
        // tokens are opaque random values compared via hash_equals /
        // hashed DB lookup - none of the three need or benefit from an
        // extra Laravel-encryption layer on top.
        $middleware->api(append: [AddQueuedCookiesToResponse::class]);

        // Applied explicitly per-route, not globally - see routes/api.php.
        $middleware->alias([
            'human' => EnsureUserIsHuman::class,
            'role' => EnsureHasRole::class,
            'permission' => EnsureHasPermission::class,
            'csrf.cookie' => VerifyCsrfCookie::class,
            'idempotent' => EnsureIdempotent::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // CLAUDE.md §16: API clients always get a safe, generic JSON error
        // shape; internal detail (stack traces, SQL) never reaches the
        // client and is left to the framework's own logging instead.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // One consistent {"message": ..., "errors"?: ...} envelope for
        // every API error, regardless of exception type. Laravel's
        // defaults are close to this already, but ModelNotFoundException
        // otherwise leaks the Eloquent class name into its message (e.g.
        // "No query results for model [App\Models\Order]") - exactly the
        // internal-structure exposure this phase was asked to avoid.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null; // fall through to Laravel's default rendering
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], $e->status);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            if ($e instanceof AuthorizationException) {
                return response()->json(['message' => $e->getMessage() ?: 'This action is unauthorized.'], 403);
            }

            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();

                return response()->json([
                    'message' => $status >= 500 ? 'Something went wrong.' : ($e->getMessage() ?: 'Request failed.'),
                ], $status);
            }

            // Security fix (SECURITY_AUDIT.md): this used to fall through to
            // Laravel's default debug renderer (full stack trace, source
            // paths, and potentially query bindings/env values) whenever
            // APP_DEBUG was true - correct for local development, but every
            // request that reaches this branch has already been confirmed
            // JSON-expecting (line 66's guard above), so there is no
            // legitimate reason for a JSON API response to ever carry a
            // debug page. A misconfigured deploy that accidentally ships
            // APP_DEBUG=true (the local-dev .env.example default) would
            // otherwise leak internals to any client. The real exception
            // detail is still captured in full by Laravel's own logging,
            // independent of this render step - `storage/logs/laravel.log`
            // locally, `LOG_CHANNEL=stderr` in Railway - so nothing is lost
            // for debugging, only what's returned to the client changes.
            return response()->json(['message' => 'Something went wrong.'], 500);
        });
    })->create();
