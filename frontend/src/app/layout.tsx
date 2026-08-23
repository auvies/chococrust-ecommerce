import type { Metadata, Viewport } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import "./globals.css";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  title: { default: "Choco Crust", template: "%s | Choco Crust" },
  description: "Choco Crust — desserts made fresh, ordered online.",
};

// Explicit mobile-first viewport: base styles target the smallest screen,
// breakpoint utilities layer up from there (CLAUDE.md §1, §20).
export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
};

// Deliberately no Header/Footer here — the public storefront's chrome
// lives in `(storefront)/layout.tsx` so /admin and /login (siblings of that
// group) don't inherit customer-facing navigation.
export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en" className={`${geistSans.variable} ${geistMono.variable} h-full antialiased`}>
      <body className="flex min-h-full flex-col bg-stone-50 text-stone-900 dark:bg-stone-950 dark:text-stone-100">
        {children}
      </body>
    </html>
  );
}
