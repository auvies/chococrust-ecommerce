"use client";

import type { ReactNode } from "react";
import { AdminAuthProvider } from "@/components/admin/AuthProvider";
import { AdminShell } from "@/components/admin/AdminShell";

/**
 * Every /admin/* route is client-rendered and gated by AdminAuthProvider,
 * which calls GET /v1/auth/me and redirects to /login if that fails or the
 * account has no permissions at all (a `customer` account). This is UX-level
 * routing protection; the backend independently authorizes every request
 * regardless of what this layout does (CLAUDE.md §8).
 */
export default function AdminLayout({ children }: { children: ReactNode }) {
  return (
    <AdminAuthProvider>
      <AdminShell>{children}</AdminShell>
    </AdminAuthProvider>
  );
}
