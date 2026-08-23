# Choco Crust — Frontend

Next.js (App Router) + TypeScript storefront/admin frontend for Choco Crust. Mobile-first via Tailwind CSS (unprefixed utility classes are the base/mobile styles; `sm:`/`md:`/`lg:` layer up from there). Designed for deployment on **Vercel**.

The public storefront (Phase 05) is built: home, categories, product detail, search, about, contact, and FAQ — all API-driven against the Phase 04 backend, with no hardcoded products or categories. The authenticated admin panel (Phase 06) is also built: `/login` plus 17 RBAC-gated management modules under `/admin/*`, client-rendered and talking to the API directly from the browser (unlike the storefront's server-rendered pages). Cart, checkout, and the AI chatbot widget are not built yet — see the root [README.md](../README.md) and [CLAUDE.md](../CLAUDE.md) for what's coming and the rules that govern it.

## Setup

```bash
npm install
cp .env.example .env.local
```

Fill in `.env.local` with your local backend URL (defaults to `http://localhost:8000/api`, matching the Laravel backend's default `artisan serve` port). `NEXT_PUBLIC_SITE_URL` (defaults to `http://localhost:3000`) is only used for canonical/OG tags and the sitemap.

## Running

```bash
npm run dev
```

## Scripts

| Command                           | Purpose                   |
| --------------------------------- | ------------------------- |
| `npm run dev`                     | Start the dev server      |
| `npm run build`                   | Production build          |
| `npm run typecheck`               | TypeScript, no emit       |
| `npm run lint`                    | ESLint                    |
| `npm run format` / `format:check` | Prettier write / check    |
| `npm run test`                    | Run the Vitest suite once |
| `npm run test:watch`              | Vitest in watch mode      |

## Project Structure Notes

- `src/lib/env.ts` — the only place `NEXT_PUBLIC_*` environment variables are read and validated. Never read `process.env` directly elsewhere.
- `src/lib/api/client.ts` — the only way frontend code calls the backend (`apiFetch`). `src/lib/api/{catalog,content,reviews,settings}.ts` wrap it with typed, endpoint-specific functions; `src/lib/api/query.ts` builds the `?filter[]=&sort=&search=` query strings the backend's `ApiQuery` helper expects.
- `src/lib/homepage.ts` — derives "Best Sellers" (by live review count, since the backend has no sales-count field) and "Offers" (by scanning `compare_at_price`) from one shared product pool per page load, to stay well under the backend's per-minute API rate limit.
- `src/lib/product.ts`, `src/lib/format.ts`, `src/lib/routes.ts`, `src/lib/seo.ts` — small, focused helpers (variant/image selection, price/date formatting, id+slug URL building, `Metadata` construction from the backend's SEO endpoint).
- `src/components/{layout,home,product,category,ui}/` — Header/Nav/Search/Footer, homepage sections, product-detail pieces (gallery, variant selector, reviews), category filter/sort controls, and shared primitives (price tag, star rating, pagination, breadcrumbs).
- `src/app/` — one route per page; `products/[id]/[slug]` and `categories/[id]/[slug]` resolve by id (the backend's actual route-model-binding key) and keep the slug in the URL for readability, redirecting if it's stale. `sitemap.ts`/`robots.ts` are built from live catalog data.
- `src/lib/logger.ts` — structured logging wrapper; swap the implementation here, not at call sites, when a provider (e.g. Sentry) is added.
- `src/app/error.tsx`, `global-error.tsx`, `not-found.tsx` — App Router error boundaries. Users only ever see a generic message; details go to the logger (CLAUDE.md §16).
- Tests are colocated next to the code they cover (e.g. `page.test.tsx` beside `page.tsx`), per the Next.js-recommended Vitest setup.

### Admin panel (Phase 06)

- `src/app/login/page.tsx`, `src/app/admin/layout.tsx`, `src/app/admin/*/page.tsx` — one route per module, all Client Components (unlike the storefront's Server Components) since every screen needs an authenticated, permission-checked session with no SEO benefit to server rendering.
- `src/components/admin/AuthProvider.tsx` — `AdminAuthProvider`/`useAdminAuth`, the one place the app asks "who is logged in and what can they do" (`GET /v1/auth/me`), and redirects unauthenticated or zero-permission (`customer`) accounts to `/login`.
- `src/components/admin/RequirePermission.tsx` — wraps a module page's content; renders "Access denied" instead of the real screen if the user lacks every listed permission. UX-level only — the backend enforces every mutation independently regardless (CLAUDE.md §8).
- `src/lib/permissions.ts` — mirrors the backend's `RolePermissionSeeder` role→permission-slug table; drives sidebar filtering and every `RequirePermission` check.
- `src/lib/api/admin/client.ts` — `adminFetch`, a thin wrapper around `apiFetch` that retries once through `POST /v1/auth/refresh` on a 401 before giving up, so a 15-minute access token expiring mid-session doesn't force a re-login.
- `src/lib/api/admin/*.ts` — one typed wrapper per admin module (orders, payments, inventory, delivery rules, etc.), mirroring the backend resources in `src/types/api.ts`.
- `src/lib/hooks/useAdminList.ts` — `useAdminList`/`useAdminResource`, shared fetch/loading/error/refetch state for every admin table and detail page.
- `src/components/admin/ui/` — shared building blocks (`DataTable`, `Modal`, form fields, `ConfirmButton`, `StatusBadge`, `AdminPagination`) every module page composes rather than reimplementing.

## What's Not Here Yet

Cart, checkout, order tracking/account pages, staff/role-assignment UI (role grants stay CLI-only by design — see ADR 0003), and the AI chatbot widget are all specified in [CLAUDE.md](../CLAUDE.md) but intentionally not implemented yet.
