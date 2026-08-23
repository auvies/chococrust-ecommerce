"use client";

import { useState, type ReactNode } from "react";
import Link from "next/link";
import { useAdminAuth } from "@/components/admin/AuthProvider";
import { Sidebar } from "@/components/admin/Sidebar";

export function AdminShell({ children }: { children: ReactNode }) {
  const { user, loading, logout } = useAdminAuth();
  const [menuOpen, setMenuOpen] = useState(false);

  if (loading) {
    return <div className="flex min-h-screen items-center justify-center text-sm text-stone-500">Loading…</div>;
  }

  if (!user) return null; // AuthProvider is already redirecting to /login.

  return (
    <div className="flex min-h-screen flex-col bg-stone-50 dark:bg-stone-950 sm:flex-row">
      <header className="flex items-center justify-between border-b border-stone-200 bg-white px-4 py-3 dark:border-stone-800 dark:bg-stone-900 sm:hidden">
        <Link href="/admin" className="font-semibold text-stone-900 dark:text-stone-100">
          Choco Crust Admin
        </Link>
        <button
          type="button"
          onClick={() => setMenuOpen((open) => !open)}
          className="rounded-md border border-stone-300 px-3 py-1.5 text-sm dark:border-stone-700"
        >
          Menu
        </button>
      </header>

      <aside
        className={`w-full shrink-0 border-b border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900 sm:block sm:w-64 sm:border-b-0 sm:border-r ${
          menuOpen ? "block" : "hidden"
        }`}
      >
        <Link href="/admin" className="mb-4 hidden text-lg font-semibold text-stone-900 dark:text-stone-100 sm:block">
          Choco Crust Admin
        </Link>
        <Sidebar />
        <div className="mt-6 border-t border-stone-200 pt-4 dark:border-stone-800">
          <p className="text-sm font-medium text-stone-900 dark:text-stone-100">{user.name}</p>
          <p className="text-xs text-stone-500 dark:text-stone-500">{user.roles.join(", ") || "staff"}</p>
          <button
            type="button"
            onClick={() => logout()}
            className="mt-3 w-full rounded-md border border-stone-300 px-3 py-1.5 text-sm font-medium text-stone-700 hover:bg-stone-100 dark:border-stone-700 dark:text-stone-300 dark:hover:bg-stone-800"
          >
            Log out
          </button>
        </div>
      </aside>

      <main className="flex-1 p-4 sm:p-8">{children}</main>
    </div>
  );
}
