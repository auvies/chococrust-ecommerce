"use client";

import Link from "next/link";
import { useState } from "react";
import { CategoryNav } from "@/components/layout/CategoryNav";
import { SearchBar } from "@/components/layout/SearchBar";
import { useCart } from "@/lib/cart";
import { useCustomerAuth } from "@/components/account/AuthProvider";
import type { Category } from "@/types/api";

const PRIMARY_LINKS = [
  { href: "/", label: "Home" },
  { href: "/about", label: "About" },
  { href: "/contact", label: "Contact" },
  { href: "/faq", label: "FAQ" },
];

export function HeaderClient({ categories }: { categories: Category[] }) {
  const [menuOpen, setMenuOpen] = useState(false);
  const { itemCount } = useCart();
  const { user, loading } = useCustomerAuth();

  return (
    <header className="sticky top-0 z-40 border-b border-stone-200 bg-white/95 backdrop-blur dark:border-stone-800 dark:bg-stone-950/95">
      <div className="mx-auto flex w-full max-w-6xl items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">
        <button
          type="button"
          onClick={() => setMenuOpen((open) => !open)}
          aria-expanded={menuOpen}
          aria-controls="mobile-nav"
          className="rounded-md p-2 text-stone-700 hover:bg-stone-100 dark:text-stone-300 dark:hover:bg-stone-800 sm:hidden"
        >
          <span className="sr-only">Toggle menu</span>
          <svg viewBox="0 0 24 24" className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth={1.8}>
            {menuOpen ? (
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 6l12 12M18 6L6 18" />
            ) : (
              <path strokeLinecap="round" strokeLinejoin="round" d="M4 7h16M4 12h16M4 17h16" />
            )}
          </svg>
        </button>

        <Link href="/" className="shrink-0 text-lg font-semibold tracking-tight text-stone-900 dark:text-stone-100">
          Choco Crust
        </Link>

        <nav aria-label="Primary" className="hidden gap-5 text-sm font-medium sm:flex">
          {PRIMARY_LINKS.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              className="text-stone-700 hover:text-amber-800 dark:text-stone-300 dark:hover:text-amber-500"
            >
              {link.label}
            </Link>
          ))}
        </nav>

        <div className="ml-auto hidden max-w-xs flex-1 sm:block">
          <SearchBar />
        </div>

        <Link
          href={loading ? "/account/login" : user ? "/account/orders" : "/account/login"}
          className="shrink-0 text-sm font-medium text-stone-700 hover:text-amber-800 dark:text-stone-300 dark:hover:text-amber-500"
        >
          {user ? "My Account" : "Login"}
        </Link>

        <Link
          href="/cart"
          aria-label={`Cart, ${itemCount} item${itemCount === 1 ? "" : "s"}`}
          className="relative shrink-0 rounded-md p-2 text-stone-700 hover:bg-stone-100 dark:text-stone-300 dark:hover:bg-stone-800"
        >
          <svg viewBox="0 0 24 24" className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth={1.8}>
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              d="M3 3h1.5l1.4 12.6a1.5 1.5 0 0 0 1.5 1.4h9.7a1.5 1.5 0 0 0 1.5-1.3L20 8H6"
            />
            <circle cx="9" cy="20" r="1.2" />
            <circle cx="17" cy="20" r="1.2" />
          </svg>
          {itemCount > 0 ? (
            <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-700 px-1 text-[10px] font-semibold text-white">
              {itemCount > 99 ? "99+" : itemCount}
            </span>
          ) : null}
        </Link>
      </div>

      <div className="border-t border-stone-100 px-4 py-2 dark:border-stone-900 sm:px-6 lg:px-8">
        <CategoryNav categories={categories} />
      </div>

      {menuOpen ? (
        <div id="mobile-nav" className="border-t border-stone-200 px-4 py-4 dark:border-stone-800 sm:hidden">
          <SearchBar className="mb-4" />
          <nav aria-label="Primary mobile" className="flex flex-col gap-3 text-sm font-medium">
            {PRIMARY_LINKS.map((link) => (
              <Link
                key={link.href}
                href={link.href}
                onClick={() => setMenuOpen(false)}
                className="text-stone-700 hover:text-amber-800 dark:text-stone-300 dark:hover:text-amber-500"
              >
                {link.label}
              </Link>
            ))}
          </nav>
        </div>
      ) : null}
    </header>
  );
}
