import type { ReactNode } from "react";
import { Header } from "@/components/layout/Header";
import { Footer } from "@/components/layout/Footer";
import { CartProvider } from "@/lib/cart";
import { CustomerAuthProvider } from "@/components/account/AuthProvider";
import { ChatWidget } from "@/components/chat/ChatWidget";

// The header/footer are catalog- and settings-driven (Header fetches
// categories, Footer fetches public settings) — the storefront renders per
// request rather than being statically generated against stale data,
// consistent with "homepage must be dynamic and API-driven". Scoped to this
// route group (not the root layout) so /admin and /login don't inherit the
// customer-facing chrome.
export const dynamic = "force-dynamic";

// CartProvider/CustomerAuthProvider are Client Components wrapping the whole
// group (cart, account login state, checkout, and the header's account/cart
// links all need them) — Header itself stays a Server Component fetching
// categories per request; only its client-rendered leaf (HeaderClient) reads
// from these providers.
export default function StorefrontLayout({ children }: { children: ReactNode }) {
  return (
    <CartProvider>
      <CustomerAuthProvider>
        <Header />
        <div className="flex flex-1 flex-col">{children}</div>
        <Footer />
        <ChatWidget />
      </CustomerAuthProvider>
    </CartProvider>
  );
}
