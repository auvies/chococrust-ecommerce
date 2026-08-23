# ADR 0003: Authentication, Authorization, and Security Foundation

**Status:** Accepted
**Date:** 2026-08-23

## Context

Phase 03 required a working auth/security layer on top of the Phase 02 schema: login, sessions, RBAC enforcement, and the supporting security controls (CSRF, rate limiting, headers, input validation) - explicitly *not* any business module. CLAUDE.md §7 specifies the shape directly: "short-lived JWT access tokens plus longer-lived refresh tokens, delivered as httpOnly, Secure, SameSite cookies... never stored in localStorage." The brief also asked for a role set beyond CLAUDE.md §8's original six roles, and for AI agent identities to be structurally distinct from human administrators.

## Decisions

### JWT is hand-rolled, not a Composer package

This sandbox has no outbound network/Composer access, so `firebase/php-jwt` or similar couldn't be installed. [`app/Support/Jwt.php`](../../backend/app/Support/Jwt.php) implements the minimum needed: HS256 encode/decode, ~130 lines, covered by [`tests/Unit/Support/JwtTest.php`](../../backend/tests/Unit/Support/JwtTest.php). The algorithm is hardcoded on both sides rather than read from the token's own header, closing the "alg confusion" / "alg:none" class of vulnerability outright - there is no code path that trusts an attacker-supplied algorithm. Signature comparison uses `hash_equals` (constant-time). This is intentionally not a general-purpose JWT library (no algorithm negotiation, no JWKS, no asymmetric keys) - if a real dependency becomes installable later, swapping it in is a contained change behind this one class.

### Three cookies, not one

- `cc_access_token` - the JWT, httpOnly, 15 min default TTL.
- `cc_refresh_token` - an opaque random value, httpOnly, scoped to `/api/v1/auth` only, 14 day default TTL. Hashed (SHA-256) before being stored in the new `refresh_tokens` table, so a database read alone can never yield a usable token - matching how `payments.gateway_reference` and `users.password` are already handled.
- `cc_csrf_token` - a random value, **not** httpOnly. This is deliberate: the frontend and backend are expected to sit on different registrable domains by default (Vercel + Railway, unless custom domains are configured), so the access/refresh cookies will likely need `SameSite=None` to be sent cross-site at all - and `SameSite=None` alone provides no CSRF protection. The CSRF cookie's value is only useful if same-origin JavaScript can read it and echo it back in `X-CSRF-Token`; [`VerifyCsrfCookie`](../../backend/app/Http/Middleware/VerifyCsrfCookie.php) then compares cookie-vs-header with `hash_equals`. Applied to `/auth/logout`, `/auth/logout-all`, and `/auth/refresh`; **not** applied to `/auth/register` or `/auth/login`, since neither has a prior session to have issued a CSRF cookie from.

### Refresh tokens rotate; reuse of a rotated-out token revokes the whole session

Every `/auth/refresh` call revokes the presented refresh token and issues a brand-new pair ([`TokenService::rotate()`](../../backend/app/Services/Auth/TokenService.php)). If an already-revoked token is presented again, that's treated as a theft signal, not an ordinary expiry: every other active refresh token for that user is revoked and the user's `token_version` is bumped, invalidating any still-live access tokens too. Ordinary logout does the single-token equivalent; `POST /auth/logout-all` does the theft-response equivalent deliberately, for a user-initiated "log out everywhere."

### Stateless access tokens get an instant revocation lever

A bare JWT is normally only as revocable as its own expiry. `users.token_version` (bumped on logout-everywhere) is embedded as a `tv` claim in every access token; [`JwtCookieGuard`](../../backend/app/Auth/JwtCookieGuard.php) rejects any token whose `tv` doesn't match the user's current value. This keeps the access token's 15-minute TTL short without waiting out that window when a session needs to die immediately (compromised device report, forced logout, etc.).

### RBAC roles: extended and renamed from CLAUDE.md §8, with a documented reason

Per the brief, the baseline table gained/changed: `admin` → `manager` (same "full operational access" scope, less ambiguous name once `super_admin` and `manager` sit side by side), `support_agent` → `support` (shortened, same scope), and a new `content_manager` role (catalog/storefront content - previously implicit inside `admin`'s scope, now separated so a non-financial role can manage products/banners/coupons without touching orders or payments). `customer` and `delivery_rider` are unchanged from §8 and still present - the brief's "such as" list was staff-role examples, not a replacement for the full set. CLAUDE.md §8 itself has been updated to match (see the diff alongside this ADR) rather than left to silently drift from what's actually seeded, per its own "reductions/renames need a documented reason" rule.

Permissions are `resource.action` slugs (`orders.manage`, `settings.manage`, ...), seeded in [`RolePermissionSeeder`](../../backend/database/seeders/RolePermissionSeeder.php) and checked via centralized route middleware (`role:...`, `permission:...`) rather than ad hoc checks scattered through controllers, per CLAUDE.md §8's explicit preference. `manager` gets every operational permission but **not** `roles.manage` or `settings.manage` - that split stays `super_admin`-only, directly per §8.

### AI agents are structurally blocked from the admin surface, twice over

1. **Data model**: `users.type` is `human` or `ai_agent`. `ai_agent` is the only value the `ai_agent` role can ever be assigned to ([`AssignRole`](../../backend/app/Console/Commands/AssignRole.php) enforces this both directions), and it is the *only* role granted the `ai.tools.use` permission - deliberately excluded even from `super_admin`'s "everything" bundle (see `RolePermissionSeeder::roles()`), because it isn't an administrative capability, it's a distinct machine-identity one.
2. **Middleware**: every `/api/v1/admin/*` route carries [`EnsureUserIsHuman`](../../backend/app/Http/Middleware/EnsureUserIsHuman.php) ahead of any role/permission check. Even in a hypothetical future bug where an `ai_agent` account somehow acquired an admin permission, this middleware still blocks it. `AuthController::login()` also refuses password-based login outright for `ai_agent`-typed accounts - that identity type was never meant to authenticate the same way a human does.

Both are exercised directly in [`RbacBoundaryTest`](../../backend/tests/Feature/Auth/RbacBoundaryTest.php).

### No default admin credentials anywhere

`DatabaseSeeder` seeds roles/permissions (safe reference data, idempotent, fine in any environment) but never a staff account. The first `super_admin` - or any role grant - is provisioned via `php artisan role:assign {email} {role}`, which only promotes an *already-registered* account and is deliberately CLI-only (no HTTP endpoint), consistent with CLAUDE.md §6's "no committed credentials" and §8's "privilege escalation is itself an audited action" (every grant writes an `audit_logs` row).

### MFA/2FA: schema and a login-flow hook, not a built flow

`users` gained `two_factor_secret` (encrypted via Laravel's `encrypted` cast, itself hidden from serialization), `two_factor_recovery_codes`, and `two_factor_confirmed_at`. `AuthController::login()` already checks `hasTwoFactorEnabled()` and would short-circuit before issuing tokens - but returns `501 Not Implemented` today, since nothing can set `two_factor_confirmed_at` yet (no TOTP enrollment endpoint, no QR provisioning, no SMS gateway). This satisfies "prepare the architecture without unnecessarily adding expensive infrastructure": the expensive parts (a TOTP library, an enrollment UI, a verification endpoint) are genuinely deferred, but the login flow is already MFA-aware rather than needing a retrofit later.

### CORS/cookies required re-adding middleware Laravel's API skeleton omits by default

Laravel 11+'s default `api` middleware group is stateless by design - it does not include `EncryptCookies` or `AddQueuedCookiesToResponse`. Since this API *is* cookie-based, `AddQueuedCookiesToResponse` was added back explicitly (`bootstrap/app.php`). `EncryptCookies` was deliberately **not** re-added: the access token is a self-verifying signed JWT and the refresh/CSRF tokens are opaque values compared via `hash_equals`/hashed DB lookup - none of the three need or benefit from an extra Laravel-encryption layer on top, and adding it would have made the tokens' actual on-the-wire format less inspectable for no security gain.

### Security headers are strict because this backend never renders HTML

`routes/web.php` returns JSON only (see its own header comment from Phase 01). [`SecurityHeaders`](../../backend/app/Http/Middleware/SecurityHeaders.php) sets a strict `Content-Security-Policy: default-src 'none'` globally without needing per-route exceptions, plus `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, and HSTS on secure requests.

### Password policy and input validation

Registration requires a 10+ character password with mixed case and a number (Laravel's `Password` validation rule), on top of bcrypt hashing via the existing `'password' => 'hashed'` cast (unchanged from Phase 01/02). All auth input goes through `FormRequest` classes (`RegisterRequest`, `LoginRequest`); controllers only ever read `->validated()`, so fields not in the schema (e.g. an attacker sending `type: "ai_agent"` or `is_active: false` in a registration payload) are silently dropped rather than trusted - verified directly in `RegistrationTest::test_registration_rejects_unknown_fields_silently_by_ignoring_them`.

## Consequences

- 2 new migrations (`users` security columns, `refresh_tokens`), 1 new model, 1 hand-rolled JWT utility, 1 custom auth guard, 5 middleware classes, 1 service class, 1 controller, 2 form requests, 1 seeder, 1 console command, and demo-only `AccessCheckController` / `ToolCheckController` routes that exist purely to exercise the RBAC pipeline (no real admin business module exists yet - that's Phase 04+).
- 57 new tests across 8 files (7 `tests/Feature/Auth/*` files plus `tests/Unit/Support/JwtTest.php`) cover: registration, login (including rate-limit lockout and generic error messages), logout, refresh/rotation/theft-detection, CSRF, security headers, and - the brief's explicit ask - authorization boundaries for every role against every demo admin/AI route. The full backend suite (Phase 01 foundation + Phase 02 schema + Phase 03 auth) stands at 73 tests, all passing.
- Every route in `routes/api.php` declares its auth/role/permission requirement explicitly in the route definition itself; there is no route left to "default" to public by omission except the three deliberately public ones (`/health`, `/auth/register`, `/auth/login`).
- Not yet covered by this phase, deferred to when the relevant business/business-adjacent work lands: TOTP-based MFA enrollment/verification, password reset flows (CLAUDE.md §7's "single-use, time-limited token sent to a verified channel" - no email/SMS channel exists yet), and any actual admin business logic behind the now-proven RBAC pipeline.
