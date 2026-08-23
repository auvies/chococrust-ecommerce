import Link from "next/link";
import type { PaginationMeta } from "@/types/api";

/** Builds page links by patching `page` onto the current query string. */
export function Pagination({
  meta,
  basePath,
  searchParams,
}: {
  meta: PaginationMeta;
  basePath: string;
  searchParams: Record<string, string | undefined>;
}) {
  if (meta.last_page <= 1) return null;

  function hrefFor(page: number): string {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(searchParams)) {
      if (value) params.set(key, value);
    }
    params.set("page", String(page));
    return `${basePath}?${params.toString()}`;
  }

  const pages = Array.from({ length: meta.last_page }, (_, i) => i + 1);

  return (
    <nav aria-label="Pagination" className="flex flex-wrap items-center justify-center gap-2 pt-6">
      {meta.current_page > 1 ? (
        <Link href={hrefFor(meta.current_page - 1)} className="rounded-md border border-stone-300 px-3 py-1.5 text-sm dark:border-stone-700">
          Previous
        </Link>
      ) : null}
      {pages.map((page) => (
        <Link
          key={page}
          href={hrefFor(page)}
          aria-current={page === meta.current_page ? "page" : undefined}
          className={`rounded-md px-3 py-1.5 text-sm ${
            page === meta.current_page
              ? "bg-stone-900 text-white dark:bg-amber-700"
              : "border border-stone-300 text-stone-700 dark:border-stone-700 dark:text-stone-300"
          }`}
        >
          {page}
        </Link>
      ))}
      {meta.current_page < meta.last_page ? (
        <Link href={hrefFor(meta.current_page + 1)} className="rounded-md border border-stone-300 px-3 py-1.5 text-sm dark:border-stone-700">
          Next
        </Link>
      ) : null}
    </nav>
  );
}
