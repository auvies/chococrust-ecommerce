import Link from "next/link";
import { categoryHref } from "@/lib/routes";
import type { Category } from "@/types/api";

/** Renders only top-level, active categories — `children` come pre-nested one level from the API. */
export function CategoryNav({ categories, className = "" }: { categories: Category[]; className?: string }) {
  const roots = categories.filter((category) => category.parent_id === null && category.is_active);

  if (roots.length === 0) return null;

  return (
    <nav aria-label="Product categories" className={className}>
      <ul className="flex gap-4 overflow-x-auto pb-1 text-sm">
        {roots.map((category) => (
          <li key={category.id} className="shrink-0">
            <Link
              href={categoryHref(category)}
              className="text-stone-700 hover:text-amber-800 dark:text-stone-300 dark:hover:text-amber-500"
            >
              {category.name}
            </Link>
          </li>
        ))}
      </ul>
    </nav>
  );
}
