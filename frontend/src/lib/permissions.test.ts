import { describe, expect, it } from "vitest";
import { ADMIN_MODULES, PERMISSIONS, hasAnyPermission, hasPermission, isStaff, visibleModules } from "./permissions";
import type { AuthUser } from "@/types/api";

function makeUser(overrides: Partial<AuthUser> = {}): AuthUser {
  return {
    id: 1,
    name: "Test User",
    email: "test@example.com",
    type: "human",
    roles: ["support"],
    permissions: [],
    two_factor_enabled: false,
    ...overrides,
  };
}

describe("hasPermission / hasAnyPermission", () => {
  it("returns false for a null/undefined user", () => {
    expect(hasPermission(null, PERMISSIONS.ordersView)).toBe(false);
    expect(hasPermission(undefined, PERMISSIONS.ordersView)).toBe(false);
  });

  it("returns true only when the exact permission slug is present", () => {
    const user = makeUser({ permissions: [PERMISSIONS.ordersView] });
    expect(hasPermission(user, PERMISSIONS.ordersView)).toBe(true);
    expect(hasPermission(user, PERMISSIONS.ordersManage)).toBe(false);
  });

  it("hasAnyPermission is true if the user holds at least one of the listed slugs", () => {
    const user = makeUser({ permissions: [PERMISSIONS.paymentsView] });
    expect(hasAnyPermission(user, [PERMISSIONS.paymentsView, PERMISSIONS.paymentsManage])).toBe(true);
    expect(hasAnyPermission(user, [PERMISSIONS.paymentsManage, PERMISSIONS.ordersRefund])).toBe(false);
  });
});

describe("isStaff", () => {
  it("is false for a customer account (type=human, zero permissions)", () => {
    expect(isStaff(makeUser({ type: "human", roles: ["customer"], permissions: [] }))).toBe(false);
  });

  it("is false for a non-human identity even with permissions (ai_agent)", () => {
    expect(isStaff(makeUser({ type: "ai_agent", roles: ["ai_agent"], permissions: [PERMISSIONS.aiToolsUse] }))).toBe(false);
  });

  it("is true for any human account holding at least one permission", () => {
    expect(isStaff(makeUser({ type: "human", roles: ["support"], permissions: [PERMISSIONS.ordersView] }))).toBe(true);
  });

  it("is false for null", () => {
    expect(isStaff(null)).toBe(false);
  });
});

describe("visibleModules", () => {
  it("always includes the dashboard (no permission requirement)", () => {
    const user = makeUser({ permissions: [] });
    const modules = visibleModules(user);
    expect(modules.some((m) => m.key === "dashboard")).toBe(true);
  });

  it("a support-role user (customers.view, orders.view, chat.*) sees Orders and Customers but not Products or Settings", () => {
    const user = makeUser({ permissions: [PERMISSIONS.customersView, PERMISSIONS.ordersView] });
    const keys = visibleModules(user).map((m) => m.key);

    expect(keys).toContain("orders");
    expect(keys).toContain("customers");
    expect(keys).not.toContain("products");
    expect(keys).not.toContain("settings");
    expect(keys).not.toContain("audit-logs");
  });

  it("super_admin-equivalent (every permission except ai.tools.use) sees every module", () => {
    const allExceptAi = Object.values(PERMISSIONS).filter((p) => p !== PERMISSIONS.aiToolsUse);
    const user = makeUser({ permissions: allExceptAi });
    const keys = visibleModules(user).map((m) => m.key);

    for (const adminModule of ADMIN_MODULES) {
      expect(keys).toContain(adminModule.key);
    }
  });

  it("a user with zero permissions (customer) sees only the dashboard", () => {
    const user = makeUser({ permissions: [] });
    const keys = visibleModules(user).map((m) => m.key);
    expect(keys).toEqual(["dashboard"]);
  });
});
