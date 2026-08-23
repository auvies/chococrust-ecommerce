"use client";

import type { ReactNode } from "react";
import { useAdminAuth } from "@/components/admin/AuthProvider";
import { Forbidden } from "@/components/admin/Forbidden";

/** Wrap a module page's content so a staff member lacking every listed permission sees a clear denial, not a confusing empty/broken screen. */
export function RequirePermission({ anyOf, children }: { anyOf: string[]; children: ReactNode }) {
  const { hasAnyPermission, loading } = useAdminAuth();

  if (loading) return null;
  if (anyOf.length > 0 && !hasAnyPermission(anyOf)) return <Forbidden />;

  return <>{children}</>;
}
