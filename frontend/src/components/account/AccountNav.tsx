"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useCustomerAuth } from "@/components/account/AuthProvider";

const LINKS = [
  { href: "/account/orders", label: "Orders" },
  { href: "/account/addresses", label: "Addresses" },
  { href: "/account/notifications", label: "Notifications" },
];

export function AccountNav() {
  const pathname = usePathname();
  const { logout } = useCustomerAuth();

  return (
    <nav aria-label="Account" className="flex items-center gap-4 border-b border-stone-200 pb-3 text-sm dark:border-stone-800">
      {LINKS.map((link) => (
        <Link
          key={link.href}
          href={link.href}
          className={
            pathname.startsWith(link.href)
              ? "font-medium text-amber-800 dark:text-amber-500"
              : "text-stone-600 hover:text-amber-800 dark:text-stone-400 dark:hover:text-amber-500"
          }
        >
          {link.label}
        </Link>
      ))}
      <button type="button" onClick={() => logout()} className="ml-auto text-stone-600 hover:text-amber-800 dark:text-stone-400 dark:hover:text-amber-500">
        Log out
      </button>
    </nav>
  );
}
