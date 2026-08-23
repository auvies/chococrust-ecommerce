import type { AuthUser } from "@/types/api";

/**
 * Mirrors the permission slugs seeded in
 * `backend/database/seeders/RolePermissionSeeder.php`. This is the single
 * place the frontend names them — every admin gate below reads from here
 * instead of scattering string literals.
 *
 * This gating is UX only: hiding a nav item or redirecting away from a page
 * a user lacks permission for. The real enforcement is the backend's own
 * permission check on every request (CLAUDE.md §8 - "a hidden UI button is
 * not a security control"). Even if a user bypassed every check below, the
 * API would still reject the underlying request.
 */
export const PERMISSIONS = {
  productsView: "products.view",
  productsManage: "products.manage",
  categoriesManage: "categories.manage",
  inventoryManage: "inventory.manage",
  ordersView: "orders.view",
  ordersManage: "orders.manage",
  ordersRefund: "orders.refund",
  paymentsView: "payments.view",
  paymentsManage: "payments.manage",
  codCollect: "cod.collect",
  codManage: "cod.manage",
  deliveriesViewOwn: "deliveries.view_own",
  deliveriesManageOwn: "deliveries.manage_own",
  deliveriesManageAll: "deliveries.manage_all",
  customersView: "customers.view",
  customersManage: "customers.manage",
  couponsManage: "coupons.manage",
  reviewsModerate: "reviews.moderate",
  contentManage: "content.manage",
  notificationsManage: "notifications.manage",
  chatView: "chat.view",
  chatRespond: "chat.respond",
  staffManage: "staff.manage",
  rolesManage: "roles.manage",
  settingsManage: "settings.manage",
  auditView: "audit.view",
  analyticsView: "analytics.view",
  aiToolsUse: "ai.tools.use",
  agentActionsManage: "agent_actions.manage",
} as const;

export function hasPermission(user: AuthUser | null | undefined, slug: string): boolean {
  return Boolean(user?.permissions.includes(slug));
}

export function hasAnyPermission(user: AuthUser | null | undefined, slugs: string[]): boolean {
  return slugs.some((slug) => hasPermission(user, slug));
}

/** True for any staff account (support/order_manager/.../super_admin) — false for `customer`, which has zero permissions. */
export function isStaff(user: AuthUser | null | undefined): boolean {
  return Boolean(user && user.type === "human" && user.permissions.length > 0);
}

export interface AdminModule {
  key: string;
  label: string;
  href: string;
  /** User needs at least one of these permissions to see/use the module. */
  permissions: string[];
}

export const ADMIN_MODULES: AdminModule[] = [
  { key: "dashboard", label: "Dashboard", href: "/admin", permissions: [] },
  { key: "products", label: "Products", href: "/admin/products", permissions: [PERMISSIONS.productsView, PERMISSIONS.productsManage] },
  { key: "categories", label: "Categories", href: "/admin/categories", permissions: [PERMISSIONS.categoriesManage] },
  { key: "orders", label: "Orders", href: "/admin/orders", permissions: [PERMISSIONS.ordersView, PERMISSIONS.ordersManage] },
  { key: "customers", label: "Customers", href: "/admin/customers", permissions: [PERMISSIONS.customersView, PERMISSIONS.customersManage] },
  { key: "inventory", label: "Inventory", href: "/admin/inventory", permissions: [PERMISSIONS.inventoryManage, PERMISSIONS.productsView] },
  { key: "payments", label: "Payments", href: "/admin/payments", permissions: [PERMISSIONS.paymentsView, PERMISSIONS.paymentsManage] },
  { key: "delivery", label: "Delivery", href: "/admin/delivery", permissions: [PERMISSIONS.deliveriesManageAll, PERMISSIONS.deliveriesViewOwn, PERMISSIONS.deliveriesManageOwn] },
  { key: "delivery-rules", label: "Delivery Rules", href: "/admin/delivery-rules", permissions: [PERMISSIONS.categoriesManage] },
  { key: "cod", label: "COD", href: "/admin/cod", permissions: [PERMISSIONS.codManage, PERMISSIONS.codCollect] },
  { key: "reviews", label: "Reviews", href: "/admin/reviews", permissions: [PERMISSIONS.reviewsModerate] },
  { key: "media", label: "Media", href: "/admin/media", permissions: [PERMISSIONS.productsManage, PERMISSIONS.contentManage] },
  { key: "hero-banners", label: "Hero Banners", href: "/admin/hero-banners", permissions: [PERMISSIONS.contentManage] },
  { key: "themes", label: "Themes", href: "/admin/themes", permissions: [PERMISSIONS.contentManage] },
  { key: "homepage", label: "Homepage", href: "/admin/homepage", permissions: [PERMISSIONS.contentManage] },
  { key: "seo", label: "SEO", href: "/admin/seo", permissions: [PERMISSIONS.contentManage] },
  { key: "notification-templates", label: "Notification Templates", href: "/admin/notification-templates", permissions: [PERMISSIONS.notificationsManage] },
  { key: "analytics", label: "Analytics", href: "/admin/analytics", permissions: [PERMISSIONS.analyticsView] },
  { key: "ai-usage", label: "AI Chatbot Usage", href: "/admin/ai-usage", permissions: [PERMISSIONS.analyticsView] },
  { key: "agent-actions", label: "Agent Actions", href: "/admin/agent-actions", permissions: [PERMISSIONS.agentActionsManage] },
  { key: "settings", label: "Settings", href: "/admin/settings", permissions: [PERMISSIONS.settingsManage] },
  { key: "audit-logs", label: "Audit Logs", href: "/admin/audit-logs", permissions: [PERMISSIONS.auditView] },
];

export function visibleModules(user: AuthUser | null | undefined): AdminModule[] {
  return ADMIN_MODULES.filter((module) => module.permissions.length === 0 || hasAnyPermission(user, module.permissions));
}
