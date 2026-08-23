import { describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import { Sidebar } from "./Sidebar";
import * as AuthProviderModule from "./AuthProvider";
import { PERMISSIONS } from "@/lib/permissions";
import type { AuthUser } from "@/types/api";

vi.mock("next/navigation", () => ({
  usePathname: () => "/admin",
}));

function mockAuth(permissions: string[]) {
  vi.spyOn(AuthProviderModule, "useAdminAuth").mockReturnValue({
    user: {
      id: 1,
      name: "Test",
      email: "test@example.com",
      type: "human",
      roles: ["support"],
      permissions,
      two_factor_enabled: false,
    } as AuthUser,
    loading: false,
    hasPermission: (slug) => permissions.includes(slug),
    hasAnyPermission: (slugs) => slugs.some((s) => permissions.includes(s)),
    logout: vi.fn(),
    refetch: vi.fn(),
  });
}

describe("Sidebar", () => {
  it("only renders modules the current user has a permission for", () => {
    mockAuth([PERMISSIONS.reviewsModerate]);

    render(<Sidebar />);

    expect(screen.getByText("Reviews")).toBeInTheDocument();
    expect(screen.queryByText("Settings")).not.toBeInTheDocument();
    expect(screen.queryByText("Categories")).not.toBeInTheDocument();
  });

  it("always renders the Dashboard link regardless of permissions", () => {
    mockAuth([]);

    render(<Sidebar />);

    expect(screen.getByText("Dashboard")).toBeInTheDocument();
  });

  it("renders every module for a user holding every permission", () => {
    mockAuth(Object.values(PERMISSIONS));

    render(<Sidebar />);

    expect(screen.getByText("Products")).toBeInTheDocument();
    expect(screen.getByText("Settings")).toBeInTheDocument();
    expect(screen.getByText("Audit Logs")).toBeInTheDocument();
  });
});
