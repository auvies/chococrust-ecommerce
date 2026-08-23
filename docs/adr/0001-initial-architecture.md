# ADR 0001: Initial Architecture

**Status:** Accepted
**Date:** 2026-08-21

## Context

Choco Crust needs a foundation before any business logic (products, orders, payments, chatbot) is built. The target platforms and languages were specified directly: Next.js/TypeScript frontend on Vercel, Laravel REST API backend on Railway, PostgreSQL database designed for Neon but required to remain provider-replaceable.

## Decision

- **Frontend**: Next.js (App Router) + TypeScript, mobile-first via Tailwind CSS (mobile-first by default — unprefixed utility classes are the base styles, breakpoint prefixes layer up), deployed on Vercel.
- **Backend**: Laravel (PHP 8.3+) REST API, deployed on Railway. No server-rendered views — API only. JSON error responses for all `/api/*` traffic.
- **Database**: PostgreSQL. Connection is entirely environment-variable driven (`config/database.php`'s `pgsql` connection); Neon is the initial provider, but nothing in the codebase assumes Neon specifically beyond requiring SSL (`DB_SSLMODE`), which any managed Postgres provider supports. Swapping providers is an environment-variable change, not a code change.
- **Separation**: frontend never talks to the database or third-party APIs directly — everything routes through the Laravel API, per [CLAUDE.md §1](../../CLAUDE.md#1-project-architecture).

## Consequences

- Two independently deployable applications (`frontend/`, `backend/`) with their own dependency trees, environment files, linting, and test suites — no shared runtime.
- The local development machine did not have a current PHP (only PHP 8.1 via an existing XAMPP install, EOL and incompatible with a non-vulnerable Laravel install). A standalone PHP 8.4 runtime was fetched into `.tooling/` for local scaffolding and command execution only; it is not part of the deployed application (Railway provides its own PHP runtime via its Laravel/PHP buildpack) and is git-ignored.
- Backend testing uses in-memory SQLite (Laravel default) for speed and independence from a live Postgres connection; this only affects the test suite, not the runtime database.
