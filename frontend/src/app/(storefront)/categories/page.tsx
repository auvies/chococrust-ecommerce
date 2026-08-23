import type { Metadata } from "next";
import { getCategories } from "@/lib/api/catalog";
import { logger } from "@/lib/logger";
import { CategoryGrid } from "@/components/home/CategoryGrid";
import { Container } from "@/components/ui/Container";
import type { Category } from "@/types/api";

export const metadata: Metadata = {
  title: "All Categories",
  description: "Browse every Choco Crust product category.",
};

export default async function CategoriesIndexPage() {
  let categories: Category[] = [];
  try {
    const result = await getCategories({ filter: { is_active: true }, sort: "sort_order", per_page: 50 });
    categories = result.data;
  } catch (error) {
    logger.error("Failed to load categories index", { message: (error as Error).message });
  }

  return (
    <main className="flex-1 py-6 sm:py-10">
      <Container className="flex flex-col gap-6">
        <h1 className="text-2xl font-semibold tracking-tight text-stone-900 dark:text-stone-100 sm:text-3xl">
          All Categories
        </h1>
        <CategoryGrid categories={categories} />
      </Container>
    </main>
  );
}
