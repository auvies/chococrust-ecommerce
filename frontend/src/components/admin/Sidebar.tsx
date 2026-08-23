"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useAdminAuth } from "@/components/admin/AuthProvider";
import { visibleModules } from "@/lib/permissions";

export function Sidebar({ className = "" }: { className?: string }) {
  const { user } = useAdminAuth();
  const pathname = usePathname();
  const modules = visibleModules(user);

  return (
    <nav aria-label="Admin sections" className={className}>
      <ul className="flex flex-col gap-1">
        {modules.map((module) => {
          const active = pathname === module.href || (module.href !== "/admin" && pathname.startsWith(module.href));
          return (
            <li key={module.key}>
              <Link
                href={module.href}
                aria-current={active ? "page" : undefined}
                className={`block rounded-md px-3 py-2 text-sm font-medium ${
                  active
                    ? "bg-stone-900 text-white dark:bg-amber-700"
                    : "text-stone-700 hover:bg-stone-100 dark:text-stone-300 dark:hover:bg-stone-800"
                }`}
              >
                {module.label}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
