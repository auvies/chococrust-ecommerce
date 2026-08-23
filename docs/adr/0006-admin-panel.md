# ADR 0006: Authenticated Admin Panel

**Status:** Accepted
**Date:** 2026-08-22

## Context

Phase 06 needed a role-gated admin panel covering 17 management modules (Dashboard, Products, Categories, Orders, Customers, Inventory, Payments, Delivery, COD, Reviews, Media, Hero Banners, Themes, Homepage, SEO, Settings, Audit Logs) on top of the Phase 03 auth layer and the Phase 04/05 API, with an explicit instruction to enforce RBAC on every module and never allow unauthorized role access.

## Decisions

### Client-rendered, not server-rendered — a deliberate departure from Phase 05

The public storefront (Phase 05) is Server Components fetching public, unauthenticated data. The admin panel is different: every screen needs an authenticated, permission-checked session, and there's no SEO/first-paint reason to pay for server rendering here. Rather than build a cookie-forwarding Server Actions layer, every `/admin/*` page is a Client Component that calls the Laravel API directly from the browser (`credentials: "include"` plus the CSRF double-submit header, added to the shared `apiFetch` in `frontend/src/lib/api/client.ts`). This works because the CSRF cookie (`cc_csrf_token`) is deliberately non-`httpOnly` for exactly this purpose (ADR 0003) - reading it in the browser and echoing it as a header is the double-submit pattern working as designed, not a workaround. It required setting `CORS_ALLOWED_ORIGINS=http://localhost:3000` in the backend's `.env`, which `.env.example` had documented since Phase 01 but no prior phase had actually exercised (the storefront's server-to-server fetches aren't subject to browser CORS at all).

### RBAC enforced at three layers, with the backend as the only one that matters for security

1. **Navigation**: `frontend/src/lib/permissions.ts` mirrors `RolePermissionSeeder`'s roles-to-permission-slugs table and filters the sidebar to modules the logged-in user holds a permission for.
2. **Route**: every module page is wrapped in `<RequirePermission anyOf={[...]}>`, so a direct URL visit to a module the user lacks permission for renders "Access denied" instead of the real screen (verified in a live browser session across three roles).
3. **Backend**: unchanged from Phase 04 - every mutation independently checks the caller's permission regardless of what the UI showed. CLAUDE.md §8 is explicit that a hidden UI button is not a security control, and this is why: (1) and (2) above are UX, entirely bypassable by anyone calling the API directly, and the system's actual security guarantee comes only from (3).

### Session handling: one central place, not scattered per-page logic

`AdminAuthProvider` (`frontend/src/components/admin/AuthProvider.tsx`) calls `GET /v1/auth/me` once per admin session load - the only sanctioned way to learn the current user's identity and permissions, since the access token is an httpOnly, hand-rolled HS256 JWT that must never be decoded client-side (ADR 0003). `adminFetch` (`frontend/src/lib/api/admin/client.ts`) wraps every admin API call with a one-shot 401-refresh-and-retry: if the 15-minute access token has expired but the 14-day refresh token is still valid, the session is transparently restored instead of forcing a re-login. This was initially missing from `AdminAuthProvider`'s own `/me` check specifically (it called the plain, non-retrying `me()` helper) - found during manual browser testing of a real login session, not by code review, and fixed by routing that call through `adminFetch` too.

### Four small, necessary backend additions, everything else frontend-only

Auditing the Phase 04 API before building against it surfaced four admin capabilities with no backing endpoint at all:

- **Delivery rules** (Category Manager's most detailed requirement): the `delivery_rules` table and model existed since Phase 02, but no controller/route ever exposed CRUD on it.
- **Product variant editing**: `UpdateProductRequest` never accepted `variants`, and no endpoint existed to add, edit, or remove a variant after a product's initial creation - meaning a price could never be changed via the API.
- **COD record listing**: `PaymentController` could `collectCod()`/`verifyCod()` a *known* record by id, but there was no `GET /cod-records` to discover which orders had one to act on.
- **Cross-product review moderation**: `GET /products/{product}/reviews` existed, but nothing listed reviews across every product for a moderation queue.

Each gap was resolved with the user's explicit direction ("add a minimal backend endpoint, following existing patterns") rather than skipping the requirement or leaving a non-functional placeholder. Every addition follows the established Phase 04 conventions exactly: a Controller + Form Request(s) (where applicable) + Resource, gated by the closest-fitting existing permission slug (`categories.manage` for delivery rules and via the parent product's `products.manage` for variants, `cod.manage` for COD listing, `reviews.moderate` for the global queue - no new permission slugs were invented), audit-logged via the same `AuditLogger::log()` call site, and covered by tests for both the allowed and denied path. 19 new backend tests (158 → 177), all passing; Pint clean.

### Category Manager, built to the full spec

The task's explicit checklist - add, edit, delete/archive, parent category, subcategories, image, SEO, sort order, active/inactive, delivery rules - maps directly to `/admin/categories` (list + create modal) and `/admin/categories/[id]` (a detail page with three sections: core fields, SEO via a shared `SeoSection` component also used by the Product Manager, and delivery rules with their own add/edit/delete flow scoped to that category). All three sections were exercised against a live backend in a browser session, including creating a delivery rule end to end.

### A real, unrelated bug found and fixed: missing test cleanup

Two of the new authorization tests (`Sidebar.test.tsx`, `RequirePermission.test.tsx`) failed on first run with stale DOM from a *previous* test's `render()` call still present, because `frontend/vitest.setup.ts` never registered Testing Library's automatic cleanup - Vitest, unlike Jest, doesn't do this by default. Every prior frontend test file happened not to depend on strict isolation between renders within the same file, so the gap was silent until this phase's tests exercised it. Fixed once, globally, in `vitest.setup.ts` (`afterEach(() => cleanup())`), benefiting every current and future test file rather than working around it per-file.

## Consequences

- 17 module pages plus `/login`, ~35 admin-specific components (shared table/form/modal primitives plus one file per module), and a typed API-client layer (`frontend/src/lib/api/admin/*.ts`) mirroring the Phase 04/06 backend resources.
- 4 new backend endpoints (delivery rules CRUD, product variant CRUD, COD listing, global review queue), 19 new backend tests (177 total, Pint clean).
- 26 new frontend tests (47 total): a permission-logic unit suite, `RequirePermission`/`AdminAuthProvider`/`Sidebar` authorization tests, and `adminFetch`'s refresh-retry behavior - plus `typecheck`/`lint`/`test`/`build` all passing and a full manual walkthrough logging in as `support`, `manager`, and `super_admin` to confirm each saw exactly the modules its seeded permissions predict.
- Deliberately out of scope: staff/role-assignment UI (role grants stay CLI-only by design, per ADR 0003 - adding an HTTP path here was never part of this phase's module list and would reopen a privilege-escalation surface that decision closed), cart/checkout, and the AI chatbot widget.
