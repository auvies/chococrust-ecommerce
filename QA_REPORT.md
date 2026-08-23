# QA Report — Choco Crust

**Date:** 2026-08-23 (Phase 15 — Application Verification, following the Security Hardening pass)
**Scope:** Full backend (`backend/`, Laravel 13/PHP 8.4) and frontend (`frontend/`, Next.js 16) test suites, plus manual scenario verification. No new features added.
**Inputs read:** `CLAUDE.md`, `README.md`, `SECURITY_AUDIT.md`.

## Headline Result

**361/361 backend tests passing. 63/63 frontend tests passing. Both full suites are 100% green after this phase's fixes.**

This is the first time in this project's history the backend suite has been both fully executed *and* fully green — every prior phase's tests were "written and manually traced but not executed" (no PHP 8.4+ runtime was available until the Security Hardening phase installed one), and the first real execution (that phase) surfaced 19 pre-existing failures across five unrelated areas. All 19 are fixed and verified in this phase — see [Fixed](#fixed-this-phase) below.

---

## Tests Executed

| Category (as requested) | Where it lives | Result |
|---|---|---|
| Unit tests | `backend/tests/Unit/*` (`JwtTest`, `ExampleTest`) | 7/7 pass |
| Feature tests | `backend/tests/Feature/*` (19 module directories, 354 tests) | 354/354 pass |
| API tests | Every module above — each is an HTTP-level integration test against real routes | included above |
| Authentication tests | `tests/Feature/Auth/{LoginTest,RegistrationTest,LogoutTest,TokenRefreshTest,CsrfProtectionTest,SecurityHeadersTest,RbacBoundaryTest}` | 51/51 pass |
| Authorization tests | `RbacBoundaryTest` (guest/customer/ai_agent blocked from every admin route) + per-module permission tests throughout every other suite | pass (see per-module breakdown below) |
| Database tests | `tests/Feature/Database/SchemaArchitectureTest` (42-table schema, hierarchy, constraints) | 12/12 pass |
| Inventory tests | `tests/Feature/Inventory/InventoryTest` (reserve/commit/release/fulfill lifecycle, concurrency, movements) | 13/13 pass |
| Order tests | `tests/Feature/Orders/OrderTest` (checkout, pricing, delivery eligibility, cancellation, status transitions) | 36/36 pass |
| Payment tests | `tests/Feature/Payments/PaymentTest` (refunds, COD, idempotency) | 19/19 pass |
| COD tests | Same file, COD-specific cases (collect/verify/fail/return, permission separation) | included above |
| Delivery tests | `tests/Feature/Delivery/DeliveryTest` (assignment, tracking, failure/return, rider scoping) | 10/10 pass |
| Frontend tests | `frontend` — Vitest + React Testing Library, 16 test files | 63/63 pass |
| Responsive tests | No automated visual-regression suite exists in this codebase (Vitest/RTL, no Playwright/Cypress) — verified manually in a browser instead, see [Scenario 17-adjacent note](#manual-scenario-verification) | manual: pass |
| AI chatbot tests | `tests/Feature/Ai/ChatBotTest` (26 tests: every deterministic intent, summarization, budget limits, provider fallback) | 26/26 pass |
| AI security tests | `tests/Feature/Ai/AiToolTest`, `tests/Feature/Agents/{AgentToolGatewayTest,AgentApprovalTest,AgentEventArchitectureTest}` (allow-list enforcement, agent-type scoping, high-risk human-approval gate, usage limits) | 33/33 pass |
| Prompt injection tests | `ChatBotTest` (inbound deflection + the new indirect-injection-via-product-description regression test from the Security Hardening phase) | included above, all pass |
| File upload tests | `tests/Feature/Media/MediaTest` (MIME/size validation, path traversal, the new executable-extension regression test) | 17/17 pass |
| Rate limit tests | `LoginTest::test_repeated_failed_logins_are_rate_limited`, `ChatBotTest`'s chat-rate-limit and AI-budget-circuit-breaker tests | pass |
| Error handling tests | No dedicated automated test for the `bootstrap/app.php` exception-handler fallback (see [Remaining Warnings](#remaining-warnings)); every module's Feature tests exercise the validation/not-found/unauthorized paths through it | pass (indirect coverage) |
| Build tests | `cd frontend && npm run build` | pass |
| Production build validation | `php artisan config:cache`, `php artisan route:cache` (both fail loudly on any config/route registration problem — neither did), `php -l` across every file in `backend/app/`, `npm run build` | all pass |

---

## Passed

- **Backend: 361/361** (100%), 1,014 assertions, ~19s.
- **Frontend: 63/63** (100%).
- `npm run typecheck`, `npm run lint`, `npm run build` — all clean, zero warnings.
- `php artisan config:cache` / `route:cache` — both succeed (these fail hard on a broken config file or a duplicate/invalid route definition, so this is a real production-boot smoke test, not just a syntax check).
- `php -l` across every file in `backend/app/` — zero syntax errors.

## Failed

**Nothing, as of the final run.** Every failure found during this phase (19 backend tests) was root-caused and fixed — see below. No frontend test failed at any point.

## Fixed This Phase

All 19 backend failures inherited from the Security Hardening phase's first-ever real test execution were investigated and fixed. None were security vulnerabilities (SECURITY_AUDIT.md already covers those separately) — all were either test-infrastructure gaps or small, real application bugs:

| # | Issue | Root cause | Fix | Files |
|---|---|---|---|---|
| 1 | 12 tests in `CategoryTest`/`ProductTest` got `419` instead of their expected status | Both test classes' `setUp()` never called `$this->withCredentials()` (a Laravel test-client method other CSRF-protected test suites in this codebase already call) — every CSRF-protected request in these two files failed CSRF validation before reaching the tested logic | Added `$this->withCredentials();` to both `setUp()` methods, matching the pattern every other CSRF-testing suite already uses | `tests/Feature/Catalog/{CategoryTest,ProductTest}.php` |
| 2 | `ContentTest::test_a_theme_can_be_previewed_by_id_without_activating_it` got `401` instead of `200` | `GET /themes/{theme}` was registered inside the `auth:api`-required route group, contradicting the route file's own comment ("the same GET works for both the public site and the admin preview") and inconsistent with `GET /themes` (list), which already returns every theme's full config — including inactive ones — with no auth at all. Gating only the single-theme lookup added no real protection since the same data was already public via the list endpoint | Moved `GET /themes/{theme}` to the public route group, matching `index()`/`active()` | `backend/routes/api/content.php` |
| 3 | `CustomerTest::test_notes_are_append_only_multiple_entries_accumulate` returned notes in the wrong order | `CustomerNoteController::index()` ordered by `created_at` alone; two notes created within the same second sort non-deterministically without a secondary tiebreaker — the same class of bug `ChatSummarizationService` already has a documented fix for elsewhere in this codebase (ADR 0012) | Added a secondary `->latest('id')` tiebreaker | `backend/app/Http/Controllers/Api/V1/Customers/CustomerNoteController.php` |
| 4 | `OrderTest::test_delivery_eligibility_can_be_previewed_without_placing_an_order` — `assertJsonPath('data.fee', 150.0)` failed | `json_encode(150.0)` drops the trailing `.0`, so the response actually decodes to the int `150`; `assertJsonPath` uses strict (`assertSame`) comparison, so the float literal in the test never matched | Test expectation corrected to the int `150` | `tests/Feature/Orders/OrderTest.php` |
| 5 | `OrderTest::test_delivery_eligibility_preview_rejects_an_ineligible_cart` got `404` instead of `422` | The test created a `customer`-role `User` but never created the associated `Customer` profile row; `OrderController::checkDeliveryEligibility()` does `$request->user()->customer()->firstOrFail()`, which threw `ModelNotFoundException` (→ 404) before the "ineligible cart" logic the test actually targets ever ran | Test now creates the `Customer` profile, matching the sibling (passing) test's setup | `tests/Feature/Orders/OrderTest.php` |
| 6–8 | 3 tests in `ChatBotTest` (`test_a_payment_methods_question_reflects_the_config_list`, `test_order_status_answers_from_the_askers_own_account`, `test_order_status_never_reveals_another_customers_order`) all got the generic AI-fallback reply instead of a deterministic answer | Two real regex bugs in `ChatIntentDetector`: (a) the `PAYMENT_METHODS` pattern's trailing `\b` required "method" to be immediately followed by a non-word character, so the natural plural "payment method**s**" never matched at all; (b) the `ORDER_STATUS` pattern only covered "order status" (that word order) and "track/where-is-my order," missing the equally natural "what's the **status of** order X" phrasing | `payment\s*method` → `payment\s*methods?`; added a `status\s+of\s+(my\s+)?order` alternative to the order-status pattern | `backend/app/Services/Ai/ChatIntentDetector.php` |

**Full suite re-run after every fix, individually and as a whole — 361/361 passing, zero regressions introduced.**

Additionally, from the Security Hardening phase immediately preceding this one (see `SECURITY_AUDIT.md` for full detail — summarized here since it's the reason the suite count is 361, not the original 359):
- Idempotency middleware race condition (High) — fixed, `EnsureIdempotent.php`.
- CSRF missing on customer address mutations + inventory module (High/Medium) — fixed.
- Upload filename extension trust (High) — fixed, `MediaUploadService.php`, +1 new test.
- Indirect prompt injection via product description (High) — fixed, `PromptInjectionGuard.php`, +1 new test.
- Debug-mode exception fallthrough (High) — fixed, `bootstrap/app.php`.
- WhatsApp PII logging (Medium) — fixed.
- Frontend had zero security headers (Medium) — fixed, `next.config.ts`.

---

## Remaining Warnings

Nothing rises to Critical or High. Carried over from `SECURITY_AUDIT.md` (not re-litigated here — see that document for full detail, risk, and recommended fix for each):

- **6 Medium-severity security findings** are documented but not fixed: no row lock on refund's remaining-balance check (a race could theoretically over-refund), `markCodReturned` missing a status precondition, `POST /ai/tools/invoke` missing CSRF, a login timing side-channel enabling account enumeration, incomplete audit logging on some customer profile field changes, and a bypassable (not absent) prompt-injection pattern list. None were assessed as Critical/High; all have a recommended fix on record.
- **7 Low-severity findings** — see `SECURITY_AUDIT.md`.
- **No dedicated automated test** exists for `bootstrap/app.php`'s exception-handler fallback branch (the debug-mode fix from the Security Hardening phase) — every other exception type is covered by existing Feature tests hitting validation/not-found/unauthorized paths, but the generic-`Throwable` fallback specifically has no test reaching it. Verified by code inspection instead (see `SECURITY_AUDIT.md`, finding H5). A dedicated `testing`-environment-only route that deliberately throws would make this automatable; not added this phase to avoid new test-only production surface.
- **No automated responsive/visual-regression suite** exists (see the Responsive row above) — manual verification only, at mobile (375px) and desktop widths, confirming the nav correctly collapses to a menu button below the `sm:` breakpoint and expands to a full link bar above it, with zero console errors beyond the expected "backend not running" fetch failures.
- **Dependency vulnerability scanning** (`composer audit`, `npm audit`) was not run this phase — flagged as a gap, not attempted.
- **No live infrastructure** — no database, payment gateway, or AI provider is connected in any environment (per README.md). Nothing in this report exercises real Postgres, a real Easypaisa/JazzCash gateway, or a real Anthropic API call; all payment/AI paths were verified against the modeled/mocked/graceful-fallback behavior this project's current phase actually has.

---

## Manual Scenario Verification

The 18 requested scenarios, mapped to the actual test(s) that cover them (all passing) or to manual verification where no live infrastructure exists to test against:

| # | Scenario | Coverage |
|---|---|---|
| 1 | Normal customer order | `OrderTest::test_checkout_creates_an_order_with_server_computed_pricing` — server (never client) computes pricing from live variant prices; full checkout→confirmation flow covered across 36 `OrderTest` cases |
| 2 | COD order | `PaymentTest` — full `collect → verify` flow, plus `test_collecting_cod_marks_the_underlying_payment_cod_collected_not_paid` (a real, deliberate state-machine distinction) |
| 3 | Easypaisa/JazzCash payment flow | `OrderTest::test_payment_method_accepts_the_configured_gateways` — these methods are modeled (a `Payment` row created at `pending`) but **not wired to a live gateway** (no account exists yet — see README.md); no live redirect/webhook flow exists to test, by design at this project phase |
| 4 | Failed payment | `PaymentTest::test_marking_cod_failed_requires_permission_and_marks_payment_failed`, `test_cod_can_only_be_marked_failed_while_awaiting_delivery` |
| 5 | Duplicate payment | `PaymentTest::test_a_retried_collect_request_with_the_same_idempotency_key_replays_instead_of_double_processing`, `test_two_concurrent_collect_attempts_with_different_keys_the_second_is_rejected` — plus the idempotency race-condition fix from `SECURITY_AUDIT.md` (H1), which is exactly what makes this scenario safe under real concurrency now, not just sequential retries |
| 6 | Duplicate order | `OrderTest::test_checkout_requires_an_idempotency_key_and_replays_on_retry` |
| 7 | Out-of-stock product | `InventoryTest::test_reserve_rejects_when_not_enough_stock_is_available` |
| 8 | Inventory reservation | `InventoryTest::test_reserve_holds_stock_and_records_a_movement`, `test_two_reservations_racing_for_the_last_units_the_second_is_rejected` (concurrency, row-locked) |
| 9 | Order cancellation | `OrderTest::test_cancelling_an_order_releases_its_stock_reservation` |
| 10 | Failed delivery | `DeliveryTest::test_a_failure_reason_is_required_when_marking_a_delivery_failed_or_returned`, `test_a_failed_delivery_records_its_reason` |
| 11 | Customer refusal | `DeliveryTest::test_returning_a_delivery_for_a_cod_order_fails_its_payment_too` (a customer refusing a COD delivery is modeled as a "returned" delivery) |
| 12 | Return | `PaymentTest::test_returning_a_cod_order_marks_the_payment_failed`, `test_returning_a_cod_order_never_downgrades_an_already_settled_payment` |
| 13 | Unauthorized admin access | `RbacBoundaryTest` (23 tests) — guest, plain customer, and `ai_agent` identities all confirmed blocked from every admin route family |
| 14 | AI attempting a restricted action | `AgentToolGatewayTest` — agent-type scoping refusals, `test_a_high_risk_refund_request_never_executes_immediately`/`test_a_high_risk_order_cancellation_request_never_executes_immediately` (queued for human approval, never immediate) |
| 15 | Prompt injection | `ChatBotTest::test_a_prompt_injection_attempt_is_deflected_without_ever_calling_the_ai_provider` + the new indirect-injection-via-product-description test from `SECURITY_AUDIT.md` (H4) |
| 16 | Invalid API requests | Every module's Form Request validation tests (e.g. `CategoryTest::test_creating_a_category_validates_input_and_rejects_unknown_fields`) — unknown fields rejected, not silently dropped, per CLAUDE.md §13 |
| 17 | Malicious file upload | `MediaTest` — non-image content rejected (`malware.php`), path-traversal-shaped filenames neutralized, and the new regression test confirming a genuinely valid image with an executable-shaped original filename (`shell.pht`) is never stored with that extension (`SECURITY_AUDIT.md`, H3) |
| 18 | Secret exposure attempt | `AuditLoggerRedactionTest` (secret-shaped keys stripped from audit logs) + the debug-mode fallthrough fix (`SECURITY_AUDIT.md`, H5) verified by code inspection — no automated test triggers the fallback path itself (see Remaining Warnings) |

---

## Security Status

**No Critical or High-severity issues remain open.** All 5 High findings from `SECURITY_AUDIT.md` were fixed and verified in the prior phase; this phase's own testing pass found zero *new* security issues (the 19 failures it found were all functional/test-infrastructure bugs, not vulnerabilities — see [Fixed This Phase](#fixed-this-phase)). 6 Medium and 7 Low findings remain open and documented in `SECURITY_AUDIT.md` with recommended fixes.

## Build Status

| Check | Result |
|---|---|
| `backend`: `php artisan test` | ✅ 361/361 pass |
| `backend`: `php artisan config:cache` | ✅ succeeds |
| `backend`: `php artisan route:cache` | ✅ succeeds |
| `backend`: `php -l` (every file in `app/`) | ✅ zero syntax errors |
| `frontend`: `npm run typecheck` | ✅ clean |
| `frontend`: `npm run lint` | ✅ clean |
| `frontend`: `npm run test` | ✅ 63/63 pass |
| `frontend`: `npm run build` | ✅ succeeds, 27 routes generated |

---

## Production Readiness

**This project is NOT production-ready, and is not being declared so.** Per this phase's explicit instructions, that determination is gated on all Critical/High issues being resolved — they are — but production-readiness is a broader bar than test-green, and several things remain genuinely open regardless of test results:

- No live PostgreSQL database, payment gateway (Easypaisa/JazzCash), or AI provider (Anthropic) is connected in any environment — everything in this report was verified against modeled/mocked/graceful-degradation behavior, not real third-party integrations.
- 6 Medium and 7 Low security findings remain open (`SECURITY_AUDIT.md`).
- Dependency vulnerability scanning (`composer audit`, `npm audit`) has not been run.
- Backup/restore has never been exercised (no live database exists to back up).
- Nothing has been deployed to Vercel/Railway/Neon; nothing has been pushed to GitHub (per this phase's explicit instructions).

What this report *does* establish: the application's own logic — auth, RBAC, checkout, inventory, payments, COD, delivery, the AI chatbot and its guardrails, and the admin surface — is fully covered by a passing automated test suite for the first time, with every Critical/High security gap found this pass closed and verified.
