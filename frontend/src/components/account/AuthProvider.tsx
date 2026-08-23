"use client";

import { createContext, useContext, type ReactNode } from "react";
import { useRouter } from "next/navigation";
import { logout as apiLogout } from "@/lib/api/auth";
import { adminFetch } from "@/lib/api/admin/client";
import { useAdminResource } from "@/lib/hooks/useAdminList";
import type { AuthUser } from "@/types/api";

interface CustomerAuthContextValue {
  user: AuthUser | null;
  loading: boolean;
  logout: () => Promise<void>;
  refetch: () => Promise<void>;
}

const CustomerAuthContext = createContext<CustomerAuthContextValue | null>(null);

/**
 * Available across the whole storefront (wraps `(storefront)/layout.tsx`)
 * so the header can show "My Account" vs "Login" without every page
 * re-fetching `/auth/me`. Unlike `AdminAuthProvider`, being logged out is a
 * completely normal state here — most of the storefront needs no account at
 * all. Pages that DO require one wrap themselves in `RequireCustomerAuth`
 * instead of this provider redirecting on its own.
 */
export function CustomerAuthProvider({ children }: { children: ReactNode }) {
  const router = useRouter();

  const { data: user, loading, refetch } = useAdminResource<AuthUser | null>(
    () => adminFetch<AuthUser>("/v1/auth/me").catch(() => null),
    null,
  );

  async function logout() {
    try {
      await apiLogout();
    } finally {
      await refetch();
      router.push("/");
    }
  }

  return <CustomerAuthContext.Provider value={{ user, loading, logout, refetch }}>{children}</CustomerAuthContext.Provider>;
}

export function useCustomerAuth(): CustomerAuthContextValue {
  const ctx = useContext(CustomerAuthContext);
  if (!ctx) throw new Error("useCustomerAuth must be used within CustomerAuthProvider");
  return ctx;
}
