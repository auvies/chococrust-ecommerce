# ADR 0004: API Foundation

**Status:** Accepted
**Date:** 2026-08-24

## Context

Phase 04 needed a working REST API over the Phase 02 schema and Phase 03 auth layer, covering 19 business domains (users, catalog, customers, inventory, orders, payments, delivery, reviews, media, themes, homepage, SEO, notifications, chat, AI tools, analytics, audit logs, settings) plus consistent versioning, validation, authorization, JSON shape, error handling, rate limiting, pagination/filtering/sorting/search, idempotency, and audit logging - without turning any of this into separate services.

## Decisions

### Modular by domain, not by microservice

Every module is a Laravel Controller + Form Request(s) + API Resource(s) inside the single `backend/` application, routed from its own file under `routes/api/{module}.php` and included by `routes/api.php`. This was an explicit instruction ("do not implement unnecessary microservices") and matches CLAUDE.md §1 ("new architectural layers... only when a concrete, current need justifies them"). One deployable, one database connection pool, module boundaries enforced by directory/namespace convention rather than network calls.

### Consistent response shape, not a hand-rolled envelope

Every successful response is a Laravel API Resource (`{"data": ...}`, `{"data": [...], "links": ..., "meta": ...}` for paginated collections) - never a raw Eloquent model or array. Every error response goes through one `render()` closure in `bootstrap/app.php` that normalizes validation (422), auth (401/403), not-found (404), and any other HTTP exception into `{"message": ..., "errors"?: ...}`, and collapses anything unexpected into a generic `{"message": "Something went wrong."}` with a 500 - closing a real gap where `ModelNotFoundException`'s default message otherwise leaks the Eloquent class name (`No query results for model [App\Models\Order]`), which is exactly the "don't expose internal database structure" this phase was asked to avoid.

### One reusable query helper instead of nineteen hand-rolled ones

[`App\Support\Api\ApiQuery`](../../backend/app/Support/Api/ApiQuery.php) wraps pagination, filtering (`?filter[column]=value`), sorting (`?sort=col,-col2`), and search (`?search=term`) behind an explicit per-controller allow-list (`->filters([...])`, `->sorts([...])`, `->searchable([...])`). A client can never filter/sort by a column the controller didn't opt into - this is also how internal-only columns (hashes, soft-delete timestamps) stay unreachable through query params even though they're never in a Resource's output either. Accepts both a plain `Builder` and an Eloquent `Relation` (e.g. `$product->reviews()`) so scoped lists don't need a workaround.

### Resources control exposure, not database structure

Every module has dedicated `*Resource` classes (26 of them) that hand-pick which columns leave the API. Concretely: `ProductVariantResource` omits `cost_price` (internal margin data); `PaymentResource` only reveals `gateway_reference` to staff with `payments.view`/`payments.manage`; `CustomerResource` only reveals staff `notes` to `customers.manage` holders; `UserResource` never includes `password`/`two_factor_secret` (already hidden at the model level via `#[Hidden]`, Phase 03). Nothing downstream ever sees a raw `Model::toArray()`.

### Idempotency is opt-in middleware, not a per-endpoint reimplementation

[`EnsureIdempotent`](../../backend/app/Http/Middleware/EnsureIdempotent.php) + a new `idempotency_keys` table implement CLAUDE.md §13 directly: attach `->middleware('idempotent')` to a route, and it becomes a hard requirement that the client send an `Idempotency-Key` header; a retried request with the same key/user/route replays the first stored response instead of re-running the side effect. Applied to order creation and payment refunds - the two "payment-affecting and order-mutating endpoints where retries are possible" CLAUDE.md §13 names explicitly. Not applied everywhere: most endpoints (category creation, review approval, ...) are either naturally idempotent or low-stakes enough that a duplicate-on-retry is a minor inconvenience, not a financial or inventory bug.

### Audit logging is one call site, used selectively

[`AuditLogger::log()`](../../backend/app/Services/Audit/AuditLogger.php) is a single static entry point wrapping `AuditLog::create()`. It's called from every module for the operations CLAUDE.md §15 names - order status changes, refunds, COD collection/verification, product/category/content changes, staff activation/deactivation, role grants (already CLI-only since Phase 03), settings changes - and deliberately *not* called for routine reads or trivial updates that don't change anything state-sensitive.

### Business logic lives in services where it's genuinely non-trivial

- [`OrderService`](../../backend/app/Services/Orders/OrderService.php): checkout (server-computed pricing from the current variant price - never trusted from the client; delivery-fee resolution via the Phase 02 `delivery_rules` table; coupon validation/application; inventory reservation) and the order status state machine (an explicit transition table, not "any status to any status").
- [`PaymentService`](../../backend/app/Services/Payments/PaymentService.php): refunds (tracked as their own ledger of `payment_transactions`, capped at what's actually left to refund) and the COD collect → verify flow, where "verify" (staff reconciliation) is the only step that flips the underlying payment to `paid` - "collect" (the rider's own claim) does not, matching the COD state machine's own design from Phase 02/ADR 0002.
- [`AiToolService`](../../backend/app/Services/Ai/AiToolService.php): a hardcoded allow-list of exactly two tools (`check_product_availability`, `get_order_status`), both read-only, both schema-validated before running, both logged to `ai_usage_logs` (success *and* refused calls) - CLAUDE.md §9's "no free-form command executed from model output" applied literally. No AI tool places an order, changes a status, or issues a refund yet - CLAUDE.md §9 requires human-in-the-loop confirmation for anything with real-world/financial effect, and that confirmation flow doesn't exist yet, so the tool isn't exposed either. This is a deliberate gap, not an oversight.

Simpler modules (themes, homepage sections, hero banners, SEO metadata, notifications, roles) use inline validation and direct Eloquent calls in their controllers - a service layer would be indirection without a behavior it's protecting.

### Object-level authorization via Policies, route-level via the Phase 03 middleware

Where "can this user see/edit *this specific row*" depends on ownership (a customer's own orders/addresses/customer-profile/chat conversations; a rider's own deliveries), a Laravel Policy encodes it (`OrderPolicy`, `AddressPolicy`, `CustomerPolicy`, `DeliveryPolicy`, `PaymentPolicy`, `ChatConversationPolicy`) and controllers call `$this->authorize(...)`. Where the question is purely "does this role/permission allow this *class* of action" (create a product, refund any payment, manage settings), the Phase 03 `permission:`/`role:` route middleware is enough on its own. Both ultimately read from the same `roles`/`permissions` tables - this is two access patterns over one source of truth, not two separate systems.

### Users vs. Customers vs. AI agents, and where role assignment lives

The new Users module (staff listing, activate/deactivate) deliberately does **not** add an HTTP endpoint for role assignment. That stays `php artisan role:assign` (CLI-only, Phase 03/ADR 0003) - adding an HTTP path here would have reopened exactly the privilege-escalation surface that design closed. Deactivating a staff account also bumps `token_version` (Phase 03's instant-revocation mechanism), so a deactivated account's existing session dies immediately rather than at its next natural token expiry.

### Two real bugs this phase's tests caught, not just the requested endpoints

- **`Relation::enforceMorphMap()` (Phase 02) broke audit logging for every model not in its small allow-list.** `AuditLogger::log()` calls `getMorphClass()` on whatever model it's given - Category and Product were mapped, nothing else was, and the *enforced* map throws rather than falling back to the FQCN. Since audit logging is now used across nearly every module, this would have 500'd the first `customers.manage` update in production. Fixed by switching to `Relation::morphMap()` (non-enforced): known models still get short, stable type strings; anything not yet listed falls back to its class name instead of hard-failing.
- **Several models' `#[Fillable(...)]` lists didn't include columns the new controllers legitimately needed to set via mass assignment** (`Review.is_approved`/`is_verified_purchase`/`approved_by`/`approved_at`; `User.is_active` for staff deactivation). Laravel silently drops non-fillable keys rather than erroring, so these failed quietly - `is_approved` stayed `null` after "creating" a review, `is_active` stayed `true` after "deactivating" a user - until each module's tests caught the mismatch between the response and the actual DB state. `Review`'s fillable list was extended (those fields are legitimately staff/system-set, matching how e.g. `Order.status` is already fillable). `User.is_active` was deliberately **left non-fillable** - it's tested elsewhere (`RegistrationTest`) that a malicious registration payload must never be able to set it - and `UserController` uses `forceFill()` instead, an explicit, narrow bypass in trusted, permission-gated code rather than a blanket loosening of the model's mass-assignment protection.

## Consequences

- 25 controllers, 26 API Resources, 17 Form Requests, 6 Policies, 5 Services, 18 model factories, and 17 route files across the 19 requested domains plus the existing auth module.
- 85 new tests (158 total across the whole backend suite, all passing) covering the authenticated/unauthenticated and authorized/unauthorized path for every module, per CLAUDE.md §18 - not just the happy path.
- Deliberately out of scope, same reasoning as prior phases: no payment gateway integration (still TBD per README), no real email/SMS notification dispatch (channel choice still TBD), no state-changing AI tool, no admin UI. All of these are natural next layers on top of what exists now, not missing pieces of this phase.
