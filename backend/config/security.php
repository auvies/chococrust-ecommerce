<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JWT Access Tokens
    |--------------------------------------------------------------------------
    |
    | Signing secret and lifetime for the short-lived access token
    | (CLAUDE.md §7). Distinct from APP_KEY so rotating the JWT signing
    | secret never touches Laravel's own encryption/session keys. Falls
    | back to APP_KEY only in local/testing so a fresh clone works without
    | extra setup - JWT_SECRET is required in staging/production (checked
    | at boot in AppServiceProvider).
    |
    */

    'jwt_secret' => env('JWT_SECRET', env('APP_KEY')),

    'access_token_ttl' => (int) env('AUTH_ACCESS_TOKEN_TTL', 15), // minutes

    'refresh_token_ttl' => (int) env('AUTH_REFRESH_TOKEN_TTL', 20160), // minutes (14 days)

    /*
    |--------------------------------------------------------------------------
    | Auth Cookies
    |--------------------------------------------------------------------------
    |
    | The access/refresh/CSRF cookies are httpOnly (except the CSRF one,
    | which JS must read to echo back), Secure outside local dev, and
    | SameSite per AUTH_COOKIE_SAMESITE. Frontend and backend are expected
    | to live on different registrable domains (Vercel + Railway) unless
    | custom domains are configured, so SameSite alone cannot be relied on
    | for CSRF protection here - see the double-submit CSRF middleware.
    |
    */

    'cookie' => [
        'access_name' => env('AUTH_ACCESS_COOKIE', 'cc_access_token'),
        'refresh_name' => env('AUTH_REFRESH_COOKIE', 'cc_refresh_token'),
        'csrf_name' => env('AUTH_CSRF_COOKIE', 'cc_csrf_token'),
        'domain' => env('AUTH_COOKIE_DOMAIN'),
        'secure' => (bool) env('AUTH_COOKIE_SECURE', env('APP_ENV', 'production') !== 'local'),
        'same_site' => env('AUTH_COOKIE_SAMESITE', 'lax'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Login Throttling
    |--------------------------------------------------------------------------
    */

    'login_throttle' => [
        'max_attempts' => (int) env('AUTH_LOGIN_MAX_ATTEMPTS', 5),
        'decay_seconds' => (int) env('AUTH_LOGIN_DECAY_SECONDS', 60),
    ],
];
