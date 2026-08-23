import type { Metadata } from "next";
import { getPublicSettings } from "@/lib/api/settings";
import { logger } from "@/lib/logger";
import { Container } from "@/components/ui/Container";

export const metadata: Metadata = {
  title: "Contact",
  description: "Get in touch with Choco Crust.",
};

/**
 * Contact details are read from the public `system_settings` API, never
 * hardcoded — if nothing has been configured yet, this page says so rather
 * than showing a fabricated phone number/address. No contact form here yet:
 * building one that doesn't actually submit anywhere would be misleading,
 * and there is no backend endpoint for it in this phase.
 */
export default async function ContactPage() {
  let settings: Record<string, string | null> = {};

  try {
    settings = await getPublicSettings();
  } catch (error) {
    logger.error("Failed to load public settings for contact page", { message: (error as Error).message });
  }

  const email = settings["contact.email"];
  const phone = settings["contact.phone"];
  const address = settings["contact.address"];
  const hasContactInfo = Boolean(email || phone || address);

  return (
    <main className="flex-1 py-10 sm:py-16">
      <Container className="flex max-w-2xl flex-col gap-6">
        <h1 className="text-2xl font-semibold tracking-tight text-stone-900 dark:text-stone-100 sm:text-3xl">
          Contact Us
        </h1>

        {hasContactInfo ? (
          <dl className="flex flex-col gap-3 text-sm text-stone-600 dark:text-stone-400">
            {phone ? (
              <div>
                <dt className="font-medium text-stone-800 dark:text-stone-200">Phone</dt>
                <dd>
                  <a href={`tel:${phone}`} className="hover:underline">
                    {phone}
                  </a>
                </dd>
              </div>
            ) : null}
            {email ? (
              <div>
                <dt className="font-medium text-stone-800 dark:text-stone-200">Email</dt>
                <dd>
                  <a href={`mailto:${email}`} className="hover:underline">
                    {email}
                  </a>
                </dd>
              </div>
            ) : null}
            {address ? (
              <div>
                <dt className="font-medium text-stone-800 dark:text-stone-200">Address</dt>
                <dd>{address}</dd>
              </div>
            ) : null}
          </dl>
        ) : (
          <p className="text-sm text-stone-500 dark:text-stone-400">
            Contact details haven&apos;t been published yet — check back soon.
          </p>
        )}
      </Container>
    </main>
  );
}
