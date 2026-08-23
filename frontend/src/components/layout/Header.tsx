import { getCategories } from "@/lib/api/catalog";
import { logger } from "@/lib/logger";
import { HeaderClient } from "@/components/layout/HeaderClient";

/**
 * Category nav is API-driven, not hardcoded — fetched here (Server
 * Component) and handed to the interactive client shell. A backend outage
 * degrades to an empty category strip rather than breaking every page's
 * header (CLAUDE.md §16).
 */
export async function Header() {
  let categories: Awaited<ReturnType<typeof getCategories>>["data"] = [];

  try {
    const result = await getCategories({ filter: { is_active: true }, sort: "sort_order", per_page: 50 });
    categories = result.data;
  } catch (error) {
    logger.error("Failed to load categories for header nav", { message: (error as Error).message });
  }

  return <HeaderClient categories={categories} />;
}
