import Link from "next/link";
import { EmptyState } from "@/components/ui/EmptyState";
import { categoryHref } from "@/lib/routes";
import type { Category } from "@/types/api";

export function CategoryGrid({ categories }: { categories: Category[] }) {
  const roots = categories.filter((category) => category.parent_id === null && category.is_active);

  if (roots.length === 0) {
    return <EmptyState message="No categories to show yet." />;
  }

  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-6">
      {roots.map((category) => (
        <Link
          key={category.id}
          href={categoryHref(category)}
          className="group flex flex-col items-center gap-2 rounded-xl border border-stone-200 bg-white p-4 text-center transition hover:shadow-md dark:border-stone-800 dark:bg-stone-900"
        >
          <div className="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-stone-100 dark:bg-stone-800">
            {category.image_url ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={category.image_url} alt="" className="h-full w-full object-cover" />
            ) : (
              <span className="text-lg font-semibold text-stone-400">{category.name.charAt(0)}</span>
            )}
          </div>
          <span className="text-sm font-medium text-stone-800 group-hover:text-amber-800 dark:text-stone-200 dark:group-hover:text-amber-500">
            {category.name}
          </span>
        </Link>
      ))}
    </div>
  );
}
