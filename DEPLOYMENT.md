# Deployment Guide — Choco Crust

**Status: preparation only.** This document describes exactly how to deploy Choco Crust to production. **Nothing has been deployed yet** — no Neon project, no Railway service, no Vercel project exists as of this document. Follow it in order when you're ready to actually deploy; until then it's the reference this phase was asked to produce.

Architecture recap (see `README.md` for full detail): **Vercel** serves the Next.js frontend (storefront + admin panel), **Railway** serves the Laravel API, **Neon** provides managed PostgreSQL. The frontend never talks to the database or any third-party API directly — everything routes through the Railway-hosted backend (CLAUDE.md §1).

---

## 0. Prerequisites

Accounts needed before starting: Vercel, Railway, Neon (or Railway's own Postgres add-on — see the note in Part 1). A GitHub repository is *not* required to deploy (Railway and Vercel both also accept CLI/manual deploys), but is the recommended path for both platforms' automatic preview-deployment and CI-gating features (CLAUDE.md §25) — **this document does not push anything to GitHub itself; that remains your explicit action.**

Generate two secrets locally before you start (never commit either value anywhere):

```bash
# APP_KEY (Laravel encryption key)
php artisan key:generate --show

# JWT_SECRET (must be distinct from APP_KEY — see Part 2, and the boot-time
# check in AppServiceProvider that now refuses to start without one)
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Paste both directly into Railway's environment-variable dashboard when you reach Part 2. Do not write them into any file in this repository.

---

## 1. Database — Neon PostgreSQL

1. Create a Neon project (or a separate **branch** per environment if using one Neon project for both staging and production — keeps data fully isolated per CLAUDE.md §10's staging/production separation without paying for two projects).
2. From Neon's dashboard, copy the connection details: host, port (`5432`), database name, username, password. Neon requires SSL — this is already handled: `config/database.php`'s `pgsql` connection reads `DB_SSLMODE` and both `.env.production.example`/`.env.staging.example` already set it to `require`.
3. Do **not** run migrations yet — that happens from Railway in Part 2, step 5, as a deliberate, separate step (CLAUDE.md §25: "migrations run as a distinct, deliberate step — never implicitly triggered by an app boot").
4. **Backups**: Neon takes automatic continuous backups with point-in-time restore on all plans (retention window depends on plan tier) — this satisfies CLAUDE.md §17's "backed up automatically on a regular schedule" requirement without any application-level backup code needing to exist. Before your first production deploy, actually open Neon's restore UI once against a throwaway branch to confirm you understand the restore flow — an untested backup is not a trusted backup (CLAUDE.md §17), and this is the one part of that principle a managed provider doesn't verify for you.
5. If you'd rather not use Neon: `config/database.php`'s `pgsql` connection is entirely env-variable driven (CLAUDE.md §22) — Railway's own managed Postgres add-on works as a drop-in replacement, same `DB_*` variables, no code change. The `sslmode` requirement may differ; confirm with whichever provider you choose.

---

## 2. Backend — Railway

### 2.1 Create the service

Connect the repository (or deploy from a local checkout via the Railway CLI), and set the service's **root directory to `backend/`** — this is a monorepo-style layout with `frontend/` and `backend/` as siblings, not a single-app repo.

Railway's Nixpacks builder auto-detects a Laravel project (composer.json + artisan present) and provisions PHP + a web server automatically — no `Procfile`/`nixpacks.toml` is committed to this repo, and none is required for a standard deploy. If Railway's auto-detected start command doesn't serve correctly for any reason, the explicit fallback is:

```
php artisan serve --host=0.0.0.0 --port=$PORT
```

(Railway injects `$PORT`; the app must bind to it, not a hardcoded port.)

### 2.2 Set environment variables

Open `ENVIRONMENT.example` at the repo root for the full list of variable *names*. Set real values directly in Railway's dashboard for this service — never in a committed file. At minimum, for a working production deploy:

| Variable | Value |
|---|---|
| `APP_NAME` | `Choco Crust` (or your preferred display name) |
| `APP_ENV` | `production` (or `staging` for a staging service) |
| `APP_DEBUG` | `false` — **never `true` in a deployed environment** (see §7 below) |
| `APP_URL` | the Railway-assigned domain, e.g. `https://choco-crust-backend.up.railway.app` (switch to a custom domain later if you set one up) |
| `APP_KEY` | the value you generated in Part 0 |
| `JWT_SECRET` | the value you generated in Part 0 — **must differ from `APP_KEY`**; the app now refuses to boot outside local/testing if it doesn't (a Phase 16 fix — see §9) |
| `CORS_ALLOWED_ORIGINS` | the exact Vercel frontend URL, e.g. `https://choco-crust.vercel.app` — comma-separate multiple origins, **never `*`** |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | from Neon (Part 1) |
| `DB_SSLMODE` | `require` |
| `AUTH_COOKIE_SECURE` | `true` |
| `AUTH_COOKIE_SAMESITE` | `none` — the frontend (Vercel) and backend (Railway) are on different registrable domains unless you've configured custom domains that share a parent domain, so the auth cookies need `SameSite=None` to be sent cross-site at all. (If you later put both apps under the same parent domain — e.g. `app.chococrust.com` and `api.chococrust.com` — you can tighten this to `lax`; the CSRF double-submit cookie is the actual cross-site protection either way, not `SameSite`, per `README.md`'s Authentication & Security section.) |
| `LOG_CHANNEL` | `stderr` — Railway captures container stdout/stderr as its log stream; this is the entire "how do logs reach me" story today (see §7) |
| `LOG_LEVEL` | `warning` (production) / `info` (staging) |
| `FILESYSTEM_DISK` | `s3` — **do not leave this as `local`** (see §5, this is the single most important non-default value in this table) |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_BUCKET` / `AWS_DEFAULT_REGION` | from your S3-compatible provider |
| `AWS_ENDPOINT` / `AWS_USE_PATH_STYLE_ENDPOINT` | only if using a non-AWS provider (e.g. Cloudflare R2 — see §5) |
| `QUEUE_CONNECTION` | `sync` (no queued jobs exist in the codebase yet — see §8) |
| `CACHE_STORE` | `database` (uses the existing `cache` table — no separate Redis needed at current scale) |
| `SESSION_DRIVER` | `database` |
| `MAIL_MAILER` | `log` until a real transport is chosen (order/payment/delivery notification emails currently just get logged — see `.env.production.example`) |

Everything else in `ENVIRONMENT.example` (Anthropic, WhatsApp, Facebook/Instagram) can stay blank/disabled — every one of those integrations is "prepared, not built" (no live account exists for any of them yet per `README.md`), and the application gracefully degrades with each left unset.

### 2.3 Health checks

Point Railway's health check at `/up` (Laravel's built-in check) or `/api/v1/health` (the application-level one this project added specifically for this purpose). Both already exist and require no configuration.

### 2.4 First deploy

Deploy the service. It will build and boot, but **the database has no schema yet** — expect requests to fail with a database error until step 5 runs. This is expected and not a rollback trigger.

### 2.5 Run migrations (deliberately, not automatically)

Via Railway's one-off command runner (dashboard "Run Command" or `railway run`), **not** as part of the boot process:

```bash
php artisan migrate --force
```

`--force` is required because `APP_ENV` isn't `local`/`testing` — Laravel prompts for confirmation in any other environment by default, and there's no interactive terminal to answer that prompt in a Railway deploy step. Do **not** run `php artisan db:seed` against production — no seeder in this codebase creates a real account (`DatabaseSeeder` gates its one seeded user behind `local`/`testing`), and the first `super_admin` is provisioned out-of-band per step 2.6, never via a seeder.

### 2.6 Provision the first admin account

There is deliberately no HTTP endpoint for this (CLAUDE.md §8 — privilege escalation is CLI-only). Register a normal account through the deployed API or frontend first, then, via Railway's command runner:

```bash
php artisan role:assign you@example.com super_admin
```

### 2.7 Storage bucket setup

Create the S3 (or R2) bucket referenced by `AWS_BUCKET` before the first product/category/media upload — `MediaUploadService` does not create it for you. Confirm the bucket's public-read policy (or CDN in front of it) actually serves files at the URL `AWS_URL`/the bucket's own endpoint would produce; this project's `local` disk was found during Phase 15's QA pass to likely serve URLs that 404 by default (private visibility), so verify this end-to-end with one real test upload post-deploy rather than assuming.

---

## 3. Frontend — Vercel

### 3.1 Create the project

Import the repository, set **Root Directory to `frontend/`**. Vercel auto-detects Next.js — no custom build command is required (`next build` is the default and matches `package.json`).

### 3.2 Set environment variables

Vercel's per-environment (Production/Preview/Development) environment variable settings — all three variables here are `NEXT_PUBLIC_*` and therefore public by definition (never treat them as secrets, and never put a real secret behind this prefix per CLAUDE.md §6):

| Variable | Value |
|---|---|
| `NEXT_PUBLIC_API_BASE_URL` | the Railway backend's URL **plus `/api`**, e.g. `https://choco-crust-backend.up.railway.app/api` — this must match exactly what `CORS_ALLOWED_ORIGINS` on the backend expects the *frontend's own* origin to be (see §4) |
| `NEXT_PUBLIC_APP_ENV` | `production` (or `preview`/`staging`, matching the Vercel environment) |
| `NEXT_PUBLIC_SITE_URL` | this app's own public URL, e.g. `https://choco-crust.vercel.app` — used only for canonical/OG tags and the sitemap |

### 3.3 Deploy

Deploy. Every subsequent pull request automatically gets a Vercel preview deployment (CLAUDE.md §20) once this is connected to a GitHub repo — the preview is what should be reviewed before merge, not just the diff.

---

## 4. Connecting the two apps

This is the step most likely to be gotten wrong, so it's called out on its own:

1. After Vercel gives you the frontend's real URL, go back to Railway and set `CORS_ALLOWED_ORIGINS` to that exact URL (scheme + host, no trailing slash, no path).
2. After Railway gives you the backend's real URL, go to Vercel and set `NEXT_PUBLIC_API_BASE_URL` to that URL + `/api`.
3. Redeploy the backend after step 1 (CORS config is read at boot, not hot-reloaded) and redeploy the frontend after step 2 (`NEXT_PUBLIC_*` values are baked in at build time, not read at runtime — a `next build` did already run in step 3.3, but if you set the env var *after* the first deploy, you must trigger a new build for it to take effect).
4. Verify: open the deployed frontend, open the browser's network tab, confirm API calls succeed (no CORS error in the console) and that the three auth cookies (`cc_access_token`, `cc_refresh_token`, `cc_csrf_token`) are actually being set after logging in.

---

## 5. Storage (object storage, not local disk)

`FILESYSTEM_DISK=local` is the default in `backend/.env.example` (correct for local development) but is **not safe for production**: Railway's filesystem is ephemeral per deploy — anything written to local disk (every product image, category image, hero banner) is lost the next time the service redeploys. `config/filesystems.php` already has a working `s3` disk definition (env-driven, works against real AWS S3 or any S3-compatible provider including Cloudflare R2 via `AWS_ENDPOINT`/`AWS_USE_PATH_STYLE_ENDPOINT`) — production just needs `FILESYSTEM_DISK=s3` plus real bucket credentials set (§2.2, §2.7). No code change is required either way.

---

## 6. Logging & error monitoring

**Logging**: `LOG_CHANNEL=stderr` routes every log line to the container's stderr stream, which Railway captures and makes viewable/searchable in its dashboard (CLAUDE.md §21) — already configured correctly in `.env.production.example`/`.env.staging.example`, no further setup needed. Vercel captures the frontend's server-side function logs the same way, automatically, with no configuration.

**Error monitoring**: there is currently **no dedicated APM/error-tracking service** (Sentry, Bugsnag, etc.) integrated in either app — errors are only visible by reading Railway's/Vercel's log streams after the fact, not proactively alerted on. This is a genuine gap for a production launch, not a false negative: `composer.json` and `package.json` were checked and neither has such a package installed. **Recommended, not implemented this phase** (adding a new third-party dependency and account is out of this phase's "do not add new features" scope): `sentry/sentry-laravel` for the backend and `@sentry/nextjs` for the frontend, following the same "prepared, not built until a real account exists" posture this project already uses for WhatsApp/Facebook/Instagram/Anthropic. Until that's added, budget for someone actively checking Railway/Vercel logs after each deploy and periodically, not for being paged.

---

## 7. Security headers, HTTPS, rate limits, debug mode

All already implemented and verified — this section is confirmation, not new setup:

- **HTTPS**: both Vercel and Railway terminate TLS automatically for their assigned domains (and for custom domains once DNS is pointed at them) — no app-level configuration needed. `AUTH_COOKIE_SECURE=true` (set in §2.2) ensures auth cookies are never sent over a plaintext connection.
- **Security headers**: the backend's `SecurityHeaders` middleware sets a restrictive CSP, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, and conditional HSTS on every API response, unconditionally. The frontend's `next.config.ts` sets the same category of headers for the HTML-serving surface (added in the Phase 15 security-hardening pass) — `script-src`/`style-src` include `'unsafe-inline'` because Next.js's own App Router injects inline hydration scripts by design (confirmed by testing a stricter policy, which broke the app); `'unsafe-eval'` is dev-only and is not present in the production CSP.
- **Rate limits**: login (5/min per email+IP), registration (5/min/IP), token refresh (10/min/IP), the general API (60/min per user-or-IP), AI tool calls (20/min), and chat (15/min) are all already configured in `AppServiceProvider::registerRateLimiters()` — nothing to set up for deployment, they're always active.
- **Debug mode**: `config/app.php` defaults `APP_DEBUG` to `false` if unset at all; `.env.production.example` and `.env.staging.example` both explicitly set it to `false`. As of Phase 15/16, the exception handler (`bootstrap/app.php`) no longer has *any* code path that returns a debug-mode stack trace to a JSON API client, regardless of `APP_DEBUG` — so even a misconfigured `APP_DEBUG=true` in production can no longer leak internals through that path. **Still set it to `false` explicitly** — this is defense in depth, not a reason to skip it.

---

## 8. Queue & cache

No queued background jobs exist anywhere in this codebase today (confirmed: zero classes implement `ShouldQueue`) — `QUEUE_CONNECTION=sync` (execute inline, no worker process) is accurate and sufficient, not a shortcut. If/when an async job is added later (per CLAUDE.md §21's "background/scheduled jobs... wired to explicit Railway services/cron"), switch to `QUEUE_CONNECTION=database` (the `jobs`/`failed_jobs` tables already exist via migration) and add a second Railway service running `php artisan queue:work` — no schema change needed to make that switch.

Cache (`CACHE_STORE=database`) uses the same Postgres database via the existing `cache` table — no separate Redis/Memcached service is required at current scale. `config/database.php`/`config/cache.php` both have Redis connection blocks already defined and fully env-driven if you outgrow this later; nothing needs to change in code, only `CACHE_STORE=redis` plus `REDIS_*` variables.

---

## 9. What changed in this phase (Phase 16)

Two things were found during this review and fixed, since "prepare for deployment" surfaced them directly:

1. **`config/security.php`'s docblock claimed a boot-time check required `JWT_SECRET` outside local/testing; that check never actually existed** (`SECURITY_AUDIT.md` finding L2). Implemented in `AppServiceProvider::validateProductionSecrets()` — the app now refuses to boot outside `local`/`testing` if `JWT_SECRET` is unset (i.e., silently equal to `APP_KEY`), turning a silent misconfiguration into an immediate, loud deploy failure instead of a security gap nobody notices. Verified: full backend suite still 361/361 passing (the check is a no-op in `local`/`testing`, which is what the suite runs as).
2. **`AWS_ENDPOINT`/`AWS_URL` were read by `config/filesystems.php`'s `s3` disk but were never documented in `.env.example`** — added, since they're required for any non-AWS S3-compatible provider (e.g. Cloudflare R2) and their absence would have meant discovering this only while actually trying to configure storage for a real deploy.

No other code changes were made this phase — everything else in this document describes configuration that already existed and was verified, not new work.

---

## 10. Post-deploy verification checklist

Run through this after the first real deploy, before considering it done:

- [ ] `GET /up` and `GET /api/v1/health` both return healthy from the public Railway URL
- [ ] `php artisan migrate --force` ran successfully (check Railway's command output, then spot-check a table exists via `php artisan tinker`)
- [ ] Register a customer account through the deployed frontend; confirm the three auth cookies are set and `GET /api/v1/auth/me` succeeds
- [ ] `php artisan role:assign` your own account to `super_admin`; log into `/login` on the deployed frontend and confirm the admin panel loads with full module visibility
- [ ] Upload one product image through the admin panel; confirm the returned URL actually loads in a browser (not a 403/404 — see §2.7)
- [ ] Place one test order through the storefront end to end (browse → cart → checkout → confirmation) with COD as the payment method
- [ ] Confirm CORS: no console errors on the deployed frontend when it calls the deployed backend
- [ ] Confirm the security headers are present on a real response (`curl -I` the deployed backend and frontend, check for `Content-Security-Policy`/`X-Frame-Options`)
- [ ] Confirm `APP_DEBUG` is `false` on the deployed backend (trigger a deliberate 404 or validation error, confirm the response is the generic safe message, not a stack trace)

---

## 11. Rollback

Both Railway and Vercel keep prior deploys and support one-click rollback to the previous build/release from their dashboards — no application-level rollback tooling is needed. **Database migrations do not automatically roll back** with a code rollback: if a deploy included a destructive migration, rolling back the *code* without also planning the *data* rollback can leave the database schema ahead of what the rolled-back code expects. Per CLAUDE.md §17, take a fresh Neon backup (or confirm the automatic one) immediately before running any destructive migration in production, specifically so this scenario has a real recovery path.

---

*Nothing in this document has been executed against real infrastructure. No deploy has happened. No code has been pushed to GitHub. This is preparation only, per this phase's explicit instructions.*
