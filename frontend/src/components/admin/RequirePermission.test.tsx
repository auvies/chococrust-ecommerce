import { describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import { RequirePermission } from "./RequirePermission";
import { AdminAuthProvider } from "./AuthProvider";
import { PERMISSIONS } from "@/lib/permissions";

const replaceMock = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace: replaceMock }),
}));

vi.mock("@/lib/api/auth", () => ({
  logout: vi.fn(),
}));

vi.mock("@/lib/api/admin/client", () => ({
  adminFetch: vi.fn(),
}));

async function renderWithUser(permissions: string[]) {
  const { adminFetch } = await import("@/lib/api/admin/client");
  vi.mocked(adminFetch).mockResolvedValue({
    id: 1,
    name: "Staff Member",
    email: "staff@example.com",
    type: "human",
    roles: ["order_manager"],
    permissions,
    two_factor_enabled: false,
  });

  render(
    <AdminAuthProvider>
      <RequirePermission anyOf={[PERMISSIONS.ordersManage]}>
        <p>Order Manager Content</p>
      </RequirePermission>
    </AdminAuthProvider>,
  );
}

describe("RequirePermission", () => {
  it("renders the protected content when the user holds the required permission", async () => {
    await renderWithUser([PERMISSIONS.ordersManage]);

    await waitFor(() => expect(screen.getByText("Order Manager Content")).toBeInTheDocument());
  });

  it("renders an access-denied message when the user lacks every required permission", async () => {
    await renderWithUser([PERMISSIONS.customersView]);

    await waitFor(() => expect(screen.getByText(/access denied/i)).toBeInTheDocument());
    expect(screen.queryByText("Order Manager Content")).not.toBeInTheDocument();
  });
});
