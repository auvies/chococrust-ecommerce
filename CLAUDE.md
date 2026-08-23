# CLAUDE.md — Choco Crust Development Rules

This file defines the **permanent engineering rules** for the Choco Crust project. It applies to every human and AI contributor (including Claude Code) working in this repository, in every phase. Rules here are not suggestions — deviating from them requires an explicit, documented decision from the project owner, not a unilateral judgment call during implementation.

Business context lives in [README.md](README.md). This file is about *how we build*, not *what we're building*.

---

## 1. Project Architecture

Choco Crust is a three-tier system:

- **Frontend** — Next.js (App Router, TypeScript, mobile-first via Tailwind CSS), deployed on **Vercel**.
- **Backend** — Laravel (PHP) REST API, deployed on **Railway**.
- **Database** — **PostgreSQL**, designed for **Neon** initially. The connection is entirely environment-variable driven so the provider remains replaceable — no code should ever assume Neon specifically beyond requiring SSL.

Supporting concerns:
- **AI chatbot** (customer-facing) and, later, **AI agents** (operational) are separate logical services behind the backend API — never called directly from the frontend.
- **Object storage** (product images, uploads) is a separate provider (e.g. S3-compatible/R2), never the app server's local disk.

Principles:
- Frontend never talks to the database or third-party APIs (payment, AI, SMS) directly. Everything routes through the backend.
- The backend is the single source of truth for business logic, authorization, and validation. Never trust client-side validation alone.
- Services are stateless where possible; session/auth state lives in the database or signed tokens, not in-memory server state, so the backend can scale horizontally.
- New architectural layers (queues, caches, microservices) are only introduced when a concrete, current need justifies them — not speculatively.

## 2. Coding Standards

- **Frontend**: TypeScript, strict mode on, no implicit `any`.
- **Backend**: PHP following Laravel/PSR-12 conventions, enforced by Laravel Pint.
- Follow the existing patterns in the codebase before introducing new ones; if none exist yet, keep it simple and idiomatic to the framework in use.
- No dead code, no commented-out code blocks, no speculative abstractions for hypothetical future needs (see root-level engineering guidance: build for what's asked, not what might be asked).
- Comments explain *why*, not *what*. Self-documenting names over explanatory comments.
- Consistent formatting via a single shared linter/formatter config (ESLint + Prettier) committed to the repo — do not hand-format.
- One responsibility per module/function. Business logic lives in services, not in route handlers or React components.
- All dates/times stored and transmitted in UTC; convert for display only.

## 3. Security Standards

- Deny-by-default: every new API route requires explicit authentication/authorization unless it is deliberately and documentedly public (e.g. product listing).
- All traffic over HTTPS only, in every environment including staging.
- Validate and sanitize all input at the API boundary using schema validation (e.g. Zod) — never trust `req.body`, query params, or headers.
- Escape/encode all output rendered in HTML to prevent XSS; rely on React's default escaping and never use `dangerouslySetInnerHTML` with unsanitized content.
- No use of `eval`, dynamic `require`/`import` of user-influenced strings, or shell command construction from user input.
- Dependencies are kept current; known-vulnerable packages (per `npm audit` on the frontend, `composer audit` on the backend, or Dependabot) are patched, not ignored.
- Security-relevant changes (auth, payments, RBAC, AI tool access) require the project owner's review before merge, even in solo-dev workflows — flag them explicitly.

## 4. Privacy Rules

- Collect only the personal data required to fulfil an order: name, phone, delivery address, and payment reference. No speculative data collection "for later."
- Customer PII (phone numbers, addresses) is never logged in plaintext application logs — mask or omit.
- No customer data is sent to third parties (AI providers, analytics, marketing tools) beyond what's strictly necessary for that integration to function, and never payment credentials.
- AI chatbot conversations may contain PII; treat chat logs with the same access controls as order data, not as free-for-all analytics fodder.
- Provide a clear mechanism (even if manual in early phases) for a customer to request their data or its deletion.

## 5. Payment Security

- **Never** store raw card numbers, CVVs, or full payment credentials in our database, logs, or codebase. This is a hard line, not a style preference.
- All non-COD payments are processed through a PCI-DSS-compliant gateway (e.g. Stripe, or a Pakistan-market gateway such as JazzCash/EasyPaisa) using their hosted checkout or tokenization flow. We store only the gateway's transaction/reference ID.
- Webhook endpoints that receive payment confirmations must verify the provider's signature before trusting the payload.
- COD orders are reconciled through the admin panel by staff, with an audit trail (see §15) — no silent status changes.
- Refund and payment-status-changing actions require an authenticated admin with the correct role (see §8) and are always audit-logged.

## 6. API Key and Secret Management

- Secrets (DB credentials, AI API keys, payment gateway keys, JWT signing secrets) live **only** in environment variables, managed via Vercel/Railway's secret/environment configuration — never committed to the repository.
- `.env*` files are always in `.gitignore`. A `.env.example` with placeholder keys (no real values) documents what's required.
- Frontend code never references a secret key. Anything prefixed for client exposure (e.g. `NEXT_PUBLIC_*`) is, by definition, public — never put a real secret behind that prefix.
- If a secret is ever accidentally committed, it is treated as compromised: rotate it immediately, don't just remove it from a future commit.
- Distinct keys per environment (development/staging/production) — production secrets are never used locally.

## 7. Authentication and Authorization

- Passwords hashed with a modern algorithm (bcrypt or argon2), never reversible encryption, never plaintext.
- Session/auth via short-lived JWT access tokens plus longer-lived refresh tokens, delivered as `httpOnly`, `Secure`, `SameSite` cookies for the web frontend — never stored in `localStorage`.
- Every backend route declares its required authentication and role explicitly; there is no "forgot to protect this route" state — it's protected unless intentionally marked public.
- Rate-limit and lock out repeated failed login attempts (see §14).
- Password reset flows use single-use, time-limited tokens sent to a verified channel — never reset via a client-supplied user ID alone.

## 8. RBAC (Role-Based Access Control)

Baseline roles (may be extended, but reductions/renames need a documented reason):

| Role | Scope |
|---|---|
| `customer` | Own account, own orders, chatbot access |
| `support` | Read customer orders/chats for support; no financial or destructive actions |
| `order_manager` | Manage order lifecycle (confirm, prepare, dispatch, mark delivered/COD collected) |
| `delivery_rider` | View and update status only for orders assigned to them |
| `content_manager` | Catalog and storefront content: products, categories, banners, coupons, reviews — no orders/payments access |
| `manager` | Full operational access: products, orders, payments, customers, refunds |
| `super_admin` | Manager + system configuration, role assignment, integrations/secrets management |
| `ai_agent` | The AI chatbot/agents' own identity — allow-listed tool access only (see §9/§23), never combined with any role above |

Renamed from earlier drafts of this table, documented per this section's own rule (see [ADR 0003](docs/adr/0003-authentication-and-authorization.md)): `admin` → `manager` (same scope, clearer once `super_admin` sits alongside it), `support_agent` → `support` (shortened, same scope). `content_manager` and `ai_agent` are additions, not renames.

- Authorization checks happen server-side on every request — a hidden UI button is not a security control.
- Role checks are centralized (middleware/guard pattern), not scattered ad hoc `if (user.role === ...)` checks duplicated across handlers.
- Privilege escalation (assigning any role) is itself an audited, `super_admin`-only action — there is no HTTP endpoint for it at all; it is a deliberately out-of-band CLI action (see ADR 0003) so no default/seeded account ever holds elevated access.
- `ai_agent` is structurally never a normal administrator: it is the only role ever granted the `ai.tools.use` permission (excluded even from `super_admin`'s otherwise-full permission set), and every admin route additionally rejects the account **type** `ai_agent` outright, independent of whatever permissions it happens to hold.

## 9. AI Security and Guardrails

- The AI chatbot and any future AI agents operate with an explicit, allow-listed set of tools/actions — never given raw database or filesystem access.
- Any AI action with a real-world or financial effect (placing an order, issuing a refund, changing order status) requires either a human-in-the-loop confirmation or a narrowly-scoped, validated tool call — never a free-form command executed from model output.
- AI responses are treated as untrusted output: sanitize before rendering (no raw HTML injection from model text) and validate any structured data (tool calls, JSON) against a schema before acting on it.
- System prompts, internal tool definitions, and API keys must never be exposed to the end user, even indirectly through the model's response.
- Log AI tool-call requests and their outcomes for auditability (see §15), distinct from raw conversation transcripts.

## 10. Prompt Injection Protection

- All user-supplied text (chat messages, product reviews, order notes) is **data**, never instructions, when passed into an AI prompt or context window.
- Never concatenate untrusted content directly into a system prompt in a way that could be interpreted as new instructions; use structured message roles (system/user/tool) provided by the model API, not string concatenation.
- If the chatbot or an agent retrieves external content (e.g. a webpage, a document), that content is explicitly framed as untrusted reference data in the prompt, not as commands.
- The model must refuse, on the backend's enforcement (not just the prompt's instruction), to reveal system prompts, secrets, or perform actions outside its allow-listed tool set — enforce this in code, not only via prompting.
- Any instruction-like text found in user input or retrieved content that attempts to override system behavior is logged and the anomaly is surfaced, not silently obeyed.

## 11. Database Access Rules

- All queries go through Eloquent/the query builder with parameterized bindings. No raw string-concatenated SQL.
- The application's DB user has least-privilege access appropriate to runtime needs; a separate, more privileged user (if any) is used only for migrations, never embedded in the running app.
- Schema changes go through tracked Laravel migrations committed to version control — never manual, undocumented changes against production.
- Destructive migrations (drop column/table) require a fresh backup immediately beforehand and, for anything touching production, explicit owner sign-off.
- Sensitive fields (e.g. full address, phone) are handled with care: indexed only where necessary, never exposed in bulk exports without a business reason.

## 12. File Upload Security

- Uploads (product images, etc.) are restricted by MIME type validated server-side (not just by file extension) and by a maximum file size.
- Uploaded files are renamed to generated identifiers (e.g. UUIDs) before storage — never trust or reuse the client-supplied filename.
- Files are stored in object storage (not the app server's local disk) and served via the storage provider or CDN, not executed or interpreted by the backend.
- Images should be validated as genuinely being images (not just correctly named) before acceptance.
- Only authenticated, authorized roles (admin/staff) can upload product/content assets; customers never get arbitrary file upload capability unless a specific, scoped feature requires it (and if so, it gets its own review).

## 13. API Security

- CORS is an explicit allow-list of known frontend origins — never `*` in production.
- All input validated against a schema at the API boundary; reject unknown/extra fields rather than silently ignoring them.
- API versioning strategy decided before the first breaking change is needed, not after.
- No verbose error details (stack traces, SQL errors, internal paths) returned to clients — return safe, generic messages; log details internally (see §16).
- Idempotency for payment-affecting and order-mutating endpoints where retries are possible (e.g. webhook handlers).

## 14. Rate Limiting

- Authentication endpoints (login, signup, password reset, OTP) have strict per-IP and per-account rate limits with backoff/lockout on repeated failures.
- The AI chatbot endpoint is rate-limited per user/session to control both abuse and cost (see §19).
- General API endpoints have a sane default rate limit to blunt scraping and brute-force attempts.
- Rate limiting is enforced server-side (middleware), never assumed to be handled by the client.

## 15. Audit Logging

- All admin/staff actions that change state — order status, refunds, product changes, role/permission changes — are recorded in an append-only audit log: who, what, when, and (where relevant) before/after values.
- Authentication events (login success/failure, password reset, role change) are logged.
- Audit logs never contain secrets, full payment credentials, or unmasked sensitive PII beyond what's operationally necessary.
- Audit logs are not user-editable or deletable through the application; only accessible to `admin`/`super_admin` for review.

## 16. Error Handling

- Centralized error handling on the backend: errors are caught, logged with full internal detail server-side, and mapped to a safe, generic client-facing message.
- Distinguish expected errors (validation failure, not found, unauthorized) — handled gracefully with proper HTTP status codes — from unexpected errors, which are logged loudly for investigation.
- The frontend never shows a raw stack trace or internal error string to the user; it shows a human-readable message and, where useful, a support/reference reference ID that maps back to the internal log entry.
- Failures in non-critical paths (e.g. AI chatbot down) degrade gracefully — they never block core flows like browsing or checkout.

## 17. Backup Principles

- PostgreSQL database is backed up automatically on a regular schedule (daily minimum) with a defined retention window.
- Backups are verified periodically by performing a test restore — an untested backup is not a trusted backup.
- A fresh backup is taken immediately before any destructive schema migration or bulk data operation in production.
- Backup access is restricted; backups may contain full customer PII and are handled with the same sensitivity as production data.

## 18. Testing Requirements

- Business logic (pricing, delivery rules, order state transitions, RBAC checks) has unit test coverage.
- API endpoints have integration tests covering the authenticated/unauthenticated and authorized/unauthorized paths, not just the happy path.
- Critical user journeys (browse → cart → checkout → order confirmation; admin order management) have end-to-end test coverage before those flows ship.
- Tests are run before merge (CI), and merging with failing tests is not acceptable practice.
- Security-sensitive code (auth, RBAC, payment handling) is not considered done without a passing test for both the allowed and the denied case.

## 19. Cost / Token Optimization

- AI (LLM) calls use the smallest/cheapest model that reliably meets the task's quality bar; reserve larger models for cases that need them.
- Conversation context sent to the AI is kept as small as necessary (summarize/truncate history) rather than growing unbounded.
- Cache AI responses and expensive computed results where correctness allows it.
- Batch and index database queries deliberately; avoid N+1 query patterns.
- Monitor usage/cost on AI provider, Railway, and Vercel dashboards; flag unexpected spend spikes rather than discovering them at the invoice.

## 20. Vercel Frontend Architecture

- Next.js App Router, TypeScript, deployed on Vercel.
- Static/marketing pages use SSG/ISR where content changes infrequently (e.g. product catalog); dynamic, user-specific pages (cart, account, order status) render per-request.
- Environment variables managed through Vercel's project settings per environment (development/preview/production) — never hardcoded.
- Every pull request gets a Vercel preview deployment before merge; the preview is what gets reviewed, not just the diff.
- Client bundle only includes what's needed for the current route; avoid shipping admin-panel code to the public storefront bundle.

## 21. Railway Backend Architecture

- Laravel (PHP) REST API service deployed on Railway, with distinct staging and production environments/services.
- Health-check endpoints (`/up` built-in, `/api/v1/health` application-level) exist for Railway's deploy checks and uptime monitoring.
- Configuration is entirely environment-variable driven — no environment-specific values hardcoded in source.
- The service is designed to scale horizontally (stateless request handling); anything stateful lives in Postgres or a dedicated cache/queue, not in process memory.
- Background/scheduled jobs (e.g. order reminders) run via Laravel's queue/scheduler wired to explicit Railway services/cron, never ad hoc long-running loops in the main API process.
- `LOG_CHANNEL=stderr` in staging/production so logs land in Railway's captured container output.

## 22. PostgreSQL Database Architecture

- PostgreSQL, designed for Neon initially; connection details are entirely environment-variable driven (`config/database.php`'s `pgsql` connection) so the provider can be swapped (Neon → Railway Postgres → Supabase → self-hosted) without any code change — only `DB_*` env vars change. `DB_SSLMODE=require` against Neon.
- Laravel migrations are the single source of truth for schema structure, committed to git.
- Consistent naming conventions (snake_case for tables/columns, singular or plural chosen once and applied everywhere).
- Foreign keys and frequently-queried columns are indexed deliberately, not as an afterthought once something is slow.
- Soft-delete vs hard-delete is decided per entity type based on business/audit needs (e.g. orders are never hard-deleted); document the choice where it's made.
- Connection pooling is configured appropriately for the deployment; serverless-adjacent frontend calls never open direct database connections — they always go through the backend.

## 23. Future AI-Agent Architecture

This section governs AI agents beyond the customer-facing chatbot (e.g. inventory, marketing, operations agents), to be built in a later phase:

- Agents are built on the same allow-listed tool-call model as §9 — no agent gets broader access than the specific tools it needs.
- Every agent action with real-world effect is logged with the reasoning/trigger that led to it, not just the outcome.
- Financially or operationally significant actions (placing supplier orders, changing prices, refunding customers) require human approval until the agent has a proven track record and the business explicitly decides to automate further.
- Agents run as their own backend-triggered processes, never given direct end-user-facing execution rights or direct database credentials.
- Rollback/undo capability is a design requirement for any agent action that modifies persistent state.

## 24. Git / GitHub Rules

- `main` is always deployable; work happens on feature branches, merged via pull request.
- No direct pushes to `main`; no force-pushes to shared branches.
- Commit messages are descriptive and explain *why* a change was made, following conventional, consistent style.
- No secrets, `.env` files, or credentials are ever committed — verify `git status`/`git diff` before staging broad changes.
- PRs touching security-sensitive areas (§3, §5–§10) are called out explicitly in the PR description for focused review.
- Nothing is pushed to a remote/GitHub without explicit user instruction to do so.

## 25. Deployment Principles

- CI (lint, typecheck, tests) must pass before any deploy.
- Changes go to staging before production where a staging environment exists for the component being changed.
- Database migrations run as a distinct, deliberate step — never implicitly triggered by an app boot in production without review.
- Deploys are designed to be zero/low-downtime; rollback path is known before deploying, not figured out during an incident.
- Feature flags or gradual rollout are preferred over big-bang releases for high-risk changes once the platform supports them.

---

*This document is permanent project governance. It should be updated deliberately, with the project owner's awareness, not silently reinterpreted during implementation.*
