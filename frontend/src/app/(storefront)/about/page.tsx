import type { Metadata } from "next";
import { Container } from "@/components/ui/Container";

export const metadata: Metadata = {
  title: "About",
  description: "About Choco Crust — desserts made fresh, delivered locally and shipped across Pakistan.",
};

export default function AboutPage() {
  return (
    <main className="flex-1 py-10 sm:py-16">
      <Container className="flex max-w-2xl flex-col gap-6">
        <h1 className="text-2xl font-semibold tracking-tight text-stone-900 dark:text-stone-100 sm:text-3xl">
          About Choco Crust
        </h1>
        <p className="text-sm leading-relaxed text-stone-600 dark:text-stone-400">
          Choco Crust is a dessert brand selling directly to customers online. We bake and prepare every
          order with care, offering local same-city delivery as well as nationwide shipping across
          Pakistan for shelf-stable treats.
        </p>
        <p className="text-sm leading-relaxed text-stone-600 dark:text-stone-400">
          Browse our full catalog from the homepage or the categories menu, and check individual product
          pages for details on each item. We&apos;re still growing this page with our full brand story —
          check back soon for more.
        </p>
      </Container>
    </main>
  );
}
