import type { ReactNode } from "react";
import Link from "next/link";
import { Container } from "@/components/ui/Container";

export function Section({
  title,
  subtitle,
  viewAllHref,
  children,
}: {
  title: string;
  subtitle?: string;
  viewAllHref?: string;
  children: ReactNode;
}) {
  return (
    <section className="py-10 sm:py-14">
      <Container>
        <div className="mb-6 flex items-end justify-between gap-4 sm:mb-8">
          <div>
            <h2 className="text-xl font-semibold tracking-tight text-stone-900 dark:text-stone-100 sm:text-2xl">
              {title}
            </h2>
            {subtitle ? (
              <p className="mt-1 text-sm text-stone-600 dark:text-stone-400">{subtitle}</p>
            ) : null}
          </div>
          {viewAllHref ? (
            <Link
              href={viewAllHref}
              className="shrink-0 text-sm font-medium text-amber-800 hover:underline dark:text-amber-500"
            >
              View all
            </Link>
          ) : null}
        </div>
        {children}
      </Container>
    </section>
  );
}
