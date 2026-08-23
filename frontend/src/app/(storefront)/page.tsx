import type { Metadata } from "next";
import type { ReactNode } from "react";
import { getCategories, getProducts } from "@/lib/api/catalog";
import { getHeroBanners, getHomepageSections } from "@/lib/api/content";
import { getFeaturedProducts, getHomepageProductData, type HomepageProductData } from "@/lib/homepage";
import { logger } from "@/lib/logger";
import { Hero } from "@/components/home/Hero";
import { CategoryGrid } from "@/components/home/CategoryGrid";
import { OffersGrid } from "@/components/home/OffersGrid";
import { Testimonials } from "@/components/home/Testimonials";
import { ProductGrid } from "@/components/product/ProductGrid";
import { Section } from "@/components/ui/Section";
import type { Category, HeroBanner, HomepageSection, Product } from "@/types/api";

export const metadata: Metadata = {
  title: "Home",
  description: "Fresh desserts, baked to order and delivered locally or shipped across Pakistan.",
};

async function safe<T>(promise: Promise<T>, label: string, fallback: T): Promise<T> {
  try {
    return await promise;
  } catch (error) {
    logger.error(`Homepage section failed to load: ${label}`, { message: (error as Error).message });
    return fallback;
  }
}

/**
 * The baseline layout, used only when the API returns zero sections (a
 * request failure, or a database with no homepage_sections rows at all -
 * shouldn't happen once `HomepageSectionSeeder` has run, but the storefront
 * must never go blank over it, CLAUDE.md §16). Mirrors
 * `HomepageSectionSeeder`'s default order.
 */
const DEFAULT_SECTIONS: HomepageSection[] = [
  { id: -1, type: "hero", title: null, config: null, sort_order: 0, is_active: true },
  { id: -2, type: "categories", title: "Shop by Category", config: null, sort_order: 1, is_active: true },
  { id: -3, type: "all_products", title: "All Products", config: null, sort_order: 2, is_active: true },
  { id: -4, type: "featured_products", title: "Featured Products", config: null, sort_order: 3, is_active: true },
  { id: -5, type: "best_sellers", title: "Best Sellers", config: null, sort_order: 4, is_active: true },
  { id: -6, type: "offers", title: "Offers", config: null, sort_order: 5, is_active: true },
  { id: -7, type: "reviews", title: "What Customers Say", config: null, sort_order: 6, is_active: true },
];

interface HomepageData {
  banners: HeroBanner[];
  categories: Category[];
  allProducts: Product[];
  featured: Product[];
  productData: HomepageProductData;
}

function renderSection(section: HomepageSection, data: HomepageData): ReactNode {
  switch (section.type) {
    case "hero":
      return <Hero key={section.id} banners={data.banners} />;

    case "categories":
      return (
        <Section key={section.id} title={section.title ?? "Shop by Category"}>
          <CategoryGrid categories={data.categories} />
        </Section>
      );

    case "all_products":
      return (
        <Section key={section.id} title={section.title ?? "All Products"} subtitle="Everything we bake and craft" viewAllHref="/search">
          <ProductGrid products={data.allProducts} emptyMessage="Products will appear here soon." />
        </Section>
      );

    case "featured_products":
      return (
        <Section key={section.id} title={section.title ?? "Featured Products"} subtitle="Handpicked favourites">
          <ProductGrid products={data.featured} emptyMessage="Featured products will appear here soon." />
        </Section>
      );

    case "best_sellers":
      return (
        <Section key={section.id} title={section.title ?? "Best Sellers"} subtitle="What customers are loving right now">
          <ProductGrid
            products={data.productData.bestSellers}
            emptyMessage="Best sellers will appear here once orders start coming in."
          />
        </Section>
      );

    case "offers":
      return (
        <Section key={section.id} title={section.title ?? "Offers"} subtitle="Limited-time markdowns">
          <OffersGrid offers={data.productData.offers} />
        </Section>
      );

    case "reviews":
      return (
        <Section key={section.id} title={section.title ?? "What Customers Say"}>
          <Testimonials testimonials={data.productData.testimonials} />
        </Section>
      );

    default:
      // Unrecognised/custom section types (content-management concern, per
      // the homepage_sections migration) are silently skipped rather than
      // breaking the page - the admin can still see and manage them.
      return null;
  }
}

/**
 * Composition, order, and visibility of every section come from
 * `homepage_sections` (admin-configurable via the Homepage Manager) - the
 * data each section needs still comes from the API at request time, no
 * hardcoded products or categories. Each data source fails independently so
 * one slow/broken endpoint never takes down the rest of the homepage
 * (CLAUDE.md §16).
 */
export default async function Home() {
  const [rawSections, banners, categories, allProducts, featured, productData] = await Promise.all([
    safe(getHomepageSections(), "homepage sections", []),
    safe(getHeroBanners(), "hero banners", []),
    safe(
      getCategories({ filter: { is_active: true }, sort: "sort_order", per_page: 50 }).then((r) => r.data),
      "categories",
      [],
    ),
    safe(
      getProducts({ filter: { status: "active" }, sort: "-created_at", per_page: 12 }).then((r) => r.data),
      "all products",
      [],
    ),
    safe(getFeaturedProducts(8), "featured products", []),
    safe(getHomepageProductData(), "best sellers / offers / reviews", {
      bestSellers: [],
      testimonials: [],
      offers: [],
    }),
  ]);

  const sections = rawSections.length > 0 ? rawSections : DEFAULT_SECTIONS;
  const data: HomepageData = { banners, categories, allProducts, featured, productData };

  // The public API only ever returns active sections, but filtering again
  // here means a disabled section can never render even if that contract
  // changes (deny-by-default, CLAUDE.md §3).
  const ordered = sections.filter((section) => section.is_active).sort((a, b) => a.sort_order - b.sort_order);

  return <main className="flex flex-1 flex-col">{ordered.map((section) => renderSection(section, data))}</main>;
}
