import { describe, expect, it, vi } from "vitest";
import { render, waitFor } from "@testing-library/react";
import { AdminAuthProvider } from "./AuthProvider";

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

describe("AdminAuthProvider — route protection", () => {
  it("redirects to /login when the session check fails (not authenticated)", async () => {
    const { adminFetch } = await import("@/lib/api/admin/client");
    vi.mocked(adminFetch).mockRejectedValue(new Error("401"));
    replaceMock.mockClear();

    render(
      <AdminAuthProvider>
        <p>Admin content</p>
      </AdminAuthProvider>,
    );

    await waitFor(() => expect(replaceMock).toHaveBeenCalledWith(expect.stringContaining("/login")));
  });

  it("redirects a `customer` account (zero permissions) away from the admin area", async () => {
    const { adminFetch } = await import("@/lib/api/admin/client");
    vi.mocked(adminFetch).mockResolvedValue({
      id: 2,
      name: "Regular Customer",
      email: "customer@example.com",
      type: "human",
      roles: ["customer"],
      permissions: [],
      two_factor_enabled: false,
    });
    replaceMock.mockClear();

    render(
      <AdminAuthProvider>
        <p>Admin content</p>
      </AdminAuthProvider>,
    );

    await waitFor(() => expect(replaceMock).toHaveBeenCalledWith("/login?error=forbidden"));
  });

  it("does not redirect a staff account with at least one permission", async () => {
    const { adminFetch } = await import("@/lib/api/admin/client");
    vi.mocked(adminFetch).mockResolvedValue({
      id: 3,
      name: "Manager",
      email: "manager@example.com",
      type: "human",
      roles: ["manager"],
      permissions: ["orders.manage"],
      two_factor_enabled: false,
    });
    replaceMock.mockClear();

    render(
      <AdminAuthProvider>
        <p>Admin content</p>
      </AdminAuthProvider>,
    );

    await waitFor(() => expect(vi.mocked(adminFetch)).toHaveBeenCalled());
    expect(replaceMock).not.toHaveBeenCalled();
  });
});
