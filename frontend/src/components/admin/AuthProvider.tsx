"use client";

import { createContext, useContext, useEffect, type ReactNode } from "react";
import { useRouter } from "next/navigation";
import { logout as apiLogout } from "@/lib/api/auth";
import { adminFetch } from "@/lib/api/admin/client";
import { useAdminResource } from "@/lib/hooks/useAdminList";
import { hasAnyPermission, hasPermission, isStaff } from "@/lib/permissions";
import type { AuthUser } from "@/types/api";

interface AuthContextValue {
  user: AuthUser | null;
  loading: boolean;
  hasPermission: (slug: string) => boolean;
  hasAnyPermission: (slugs: string[]) => boolean;
  logout: () => Promise<void>;
  refetch: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

/**
 * The one place the admin app asks "who is logged in and what can they do".
 * Never decodes the JWT itself (it's httpOnly and hand-rolled HS256 on the
 * backend) — always defers to GET /v1/auth/me. This is UX-level gating
 * only; the backend enforces every permission independently on every
 * request regardless of what this context shows (CLAUDE.md §8).
 */
export function AdminAuthProvider({ children }: { children: ReactNode }) {
  const router = useRouter();

  // Routed through adminFetch (not the plain `me()` helper) so a session
  // whose 15-minute access token has expired but whose refresh token is
  // still valid gets one silent refresh-and-retry instead of an
  // unnecessary logout — discovered by testing a real login session.
  const { data: user, loading, error, refetch } = useAdminResource<AuthUser | null>(
    () => adminFetch<AuthUser>("/v1/auth/me"),
    null,
  );

  // A failed /me (not logged in) or a `customer` account (zero
  // permissions) reaching this route both redirect away — navigation is an
  // external-system side effect, exactly what useEffect is for.
  useEffect(() => {
    if (loading) return;
    if (error) {
      router.replace(`/login?redirect=${encodeURIComponent(window.location.pathname)}`);
    } else if (!isStaff(user)) {
      router.replace("/login?error=forbidden");
    }
  }, [loading, error, user, router]);

  async function logout() {
    try {
      await apiLogout();
    } finally {
      router.replace("/login");
    }
  }

  return (
    <AuthContext.Provider
      value={{
        user: isStaff(user) ? user : null,
        loading,
        hasPermission: (slug) => hasPermission(user, slug),
        hasAnyPermission: (slugs) => hasAnyPermission(user, slugs),
        logout,
        refetch,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAdminAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAdminAuth must be used within AdminAuthProvider");
  return ctx;
}
