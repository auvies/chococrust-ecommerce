# Choco Crust — Backend

Laravel REST API backend for Choco Crust. Designed to run on **Railway**, talking to a **PostgreSQL** database (Neon initially, but the connection is entirely env-driven so the provider can be swapped without code changes — see [`config/database.php`](config/database.php)).

This is a foundation-phase scaffold: framework, configuration, health checks, error handling, logging, and testing/linting are in place. No business modules (products, orders, auth, payments, chatbot) exist yet — see the root [README.md](../README.md) and [CLAUDE.md](../CLAUDE.md) for what's coming and the rules that govern it.

## Requirements

- PHP 8.3+ with the `pdo_pgsql` extension
- Composer 2.x
- A PostgreSQL database (local, Docker, or a Neon branch) for anything beyond running the test suite

This repository does not assume PHP/Composer are globally installed on your machine — any PHP 8.3+/Composer setup works.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Fill in `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env` with your database credentials (a Neon connection string's parts, or a local Postgres instance). See `.env.staging.example` and `.env.production.example` for how configuration differs per environment — those files are documentation only and are never loaded automatically; real staging/production values live in Railway's environment variable settings, never in the repo.

## Running

```bash
php artisan serve
```

Health checks:
- `GET /up` — Laravel's built-in liveness check.
- `GET /api/v1/health` — API-level health check returning service status as JSON.

## Testing

```bash
php artisan test
```

Tests run against an in-memory SQLite database (configured in `phpunit.xml`) so the suite is fast and has no external dependency — this is independent of the Postgres connection used at runtime.

## Linting / Formatting

```bash
vendor/bin/pint          # fix code style
vendor/bin/pint --test   # check only, no changes (used in CI)
```

## Project Structure Notes

- `routes/api.php` — versioned API routes (`/api/v1/...`). All future business endpoints go here.
- `routes/web.php` — minimal; this backend serves no HTML frontend.
- `bootstrap/app.php` — central place where routing, middleware, and exception rendering are configured. API requests always get JSON error responses (CLAUDE.md §16).
- `config/cors.php` — CORS is an explicit allow-list driven by `CORS_ALLOWED_ORIGINS`; never a wildcard (CLAUDE.md §13).
- `config/database.php` — `pgsql` is the default connection; every value comes from the environment, including `DB_SSLMODE` (required by Neon).
- `config/logging.php` — `stderr` channel is available for Railway (which captures container stdout/stderr); set `LOG_CHANNEL=stderr` in staging/production.

## What's Not Here Yet

Authentication, RBAC, the product/order/payment domain, the AI chatbot integration, rate limiting, and audit logging are all specified in [CLAUDE.md](../CLAUDE.md) but intentionally not implemented in this foundation phase.
