<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Double-submit CSRF protection for the cookie-authenticated API.
 *
 * The frontend/backend are expected to sit on different registrable
 * domains by default (Vercel + Railway, unless custom domains are
 * configured later), which means the auth cookies may need
 * SameSite=None to be sent cross-site at all - and SameSite=None cannot,
 * by itself, defend against CSRF. This middleware is the actual
 * protection: the CSRF cookie is readable by frontend JS (unlike the
 * access/refresh cookies), so only a same-origin script that can read
 * it will be able to echo it back in the X-CSRF-Token header. A
 * cross-site attacker's form/fetch can make the browser attach cookies,
 * but can't read cookie values to forge the header.
 */
class VerifyCsrfCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $cookieName = config('security.cookie.csrf_name');
        $cookieValue = $request->cookie($cookieName);
        $headerValue = $request->header('X-CSRF-Token');

        if (
            ! is_string($cookieValue) || $cookieValue === ''
            || ! is_string($headerValue) || $headerValue === ''
            || ! hash_equals($cookieValue, $headerValue)
        ) {
            abort(419, 'CSRF token mismatch.');
        }

        return $next($request);
    }
}
