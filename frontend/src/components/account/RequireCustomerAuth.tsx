"use client";

import { useEffect, type ReactNode } from "react";
import { useRouter, usePathname } from "next/navigation";
import { useCustomerAuth } from "@/components/account/AuthProvider";

/** Gates a storefront page (order history, addresses, checkout) behind a logged-in customer session — UX only, the backend independently enforces ownership on every request (CLAUDE.md §8). */
export function RequireCustomerAuth({ children }: { children: ReactNode }) {
  const { user, loading } = useCustomerAuth();
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    if (!loading && !user) {
      router.replace(`/account/login?redirect=${encodeURIComponent(pathname)}`);
    }
  }, [loading, user, pathname, router]);

  if (loading || !user) {
    return <p className="p-6 text-sm text-stone-500 dark:text-stone-400">Loading…</p>;
  }

  return <>{children}</>;
}
