import { describe, expect, it, vi } from "vitest";
import { render, waitFor } from "@testing-library/react";
import { CustomerAuthProvider } from "./AuthProvider";
import { RequireCustomerAuth } from "./RequireCustomerAuth";

const replaceMock = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace: replaceMock, push: vi.fn() }),
  usePathname: () => "/checkout",
}));

vi.mock("@/lib/api/auth", () => ({
  logout: vi.fn(),
}));

vi.mock("@/lib/api/admin/client", () => ({
  adminFetch: vi.fn(),
}));

describe("RequireCustomerAuth", () => {
  it("redirects to /account/login with a redirect back to the current page when logged out", async () => {
    const { adminFetch } = await import("@/lib/api/admin/client");
    vi.mocked(adminFetch).mockRejectedValue(new Error("401"));
    replaceMock.mockClear();

    render(
      <CustomerAuthProvider>
        <RequireCustomerAuth>
          <p>Protected content</p>
        </RequireCustomerAuth>
      </CustomerAuthProvider>,
    );

    await waitFor(() => expect(replaceMock).toHaveBeenCalledWith("/account/login?redirect=%2Fcheckout"));
  });

  it("renders the protected content once a customer session is confirmed", async () => {
    const { adminFetch } = await import("@/lib/api/admin/client");
    vi.mocked(adminFetch).mockResolvedValue({
      id: 5,
      name: "Amina Khan",
      email: "amina@example.com",
      type: "human",
      roles: ["customer"],
      permissions: [],
      two_factor_enabled: false,
    });
    replaceMock.mockClear();

    const { findByText } = render(
      <CustomerAuthProvider>
        <RequireCustomerAuth>
          <p>Protected content</p>
        </RequireCustomerAuth>
      </CustomerAuthProvider>,
    );

    expect(await findByText("Protected content")).toBeInTheDocument();
    expect(replaceMock).not.toHaveBeenCalled();
  });
});
