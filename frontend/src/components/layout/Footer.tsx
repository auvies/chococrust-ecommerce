import Link from "next/link";
import { getPublicSettings } from "@/lib/api/settings";
import { logger } from "@/lib/logger";
import { Container } from "@/components/ui/Container";

const FOOTER_LINKS = [
  { href: "/", label: "Home" },
  { href: "/about", label: "About" },
  { href: "/contact", label: "Contact" },
  { href: "/faq", label: "FAQ" },
];

/**
 * Contact details come from the public `system_settings` rows
 * (`is_public = true`) when a store manager has configured them — nothing
 * here is a hardcoded phone number/address (CLAUDE.md §1: backend is the
 * single source of truth). Omitted entirely if not yet configured, rather
 * than showing placeholder/fabricated contact info.
 */
export async function Footer() {
  let settings: Record<string, string | null> = {};

  try {
    settings = await getPublicSettings();
  } catch (error) {
    logger.error("Failed to load public settings for footer", { message: (error as Error).message });
  }

  const email = settings["contact.email"];
  const phone = settings["contact.phone"];
  const address = settings["contact.address"];
  const hasContactInfo = Boolean(email || phone || address);

  return (
    <footer className="mt-auto border-t border-stone-200 bg-stone-50 dark:border-stone-800 dark:bg-stone-950">
      <Container className="flex flex-col gap-8 py-10 sm:flex-row sm:justify-between">
        <div>
          <p className="text-lg font-semibold text-stone-900 dark:text-stone-100">Choco Crust</p>
          <p className="mt-2 max-w-xs text-sm text-stone-600 dark:text-stone-400">
            Desserts made fresh, delivered locally and shipped across Pakistan.
          </p>
        </div>

        <nav aria-label="Footer" className="flex flex-col gap-2 text-sm">
          {FOOTER_LINKS.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              className="text-stone-600 hover:text-amber-800 dark:text-stone-400 dark:hover:text-amber-500"
            >
              {link.label}
            </Link>
          ))}
        </nav>

        {hasContactInfo ? (
          <div className="flex flex-col gap-1 text-sm text-stone-600 dark:text-stone-400">
            {phone ? <p>{phone}</p> : null}
            {email ? <p>{email}</p> : null}
            {address ? <p>{address}</p> : null}
          </div>
        ) : null}
      </Container>

      <div className="border-t border-stone-200 py-4 text-center text-xs text-stone-500 dark:border-stone-800 dark:text-stone-500">
        © {new Date().getFullYear()} Choco Crust. All rights reserved.
      </div>
    </footer>
  );
}
