import type { PaginationMeta } from "@/types/api";

export function AdminPagination({ meta, page, onPageChange }: { meta: PaginationMeta; page: number; onPageChange: (page: number) => void }) {
  if (meta.last_page <= 1) return null;

  return (
    <div className="mt-3 flex items-center justify-between text-sm text-stone-600 dark:text-stone-400">
      <span>
        {meta.total} total — page {meta.current_page} of {meta.last_page}
      </span>
      <div className="flex gap-2">
        <button
          type="button"
          disabled={page <= 1}
          onClick={() => onPageChange(page - 1)}
          className="rounded-md border border-stone-300 px-3 py-1 disabled:opacity-40 dark:border-stone-700"
        >
          Previous
        </button>
        <button
          type="button"
          disabled={page >= meta.last_page}
          onClick={() => onPageChange(page + 1)}
          className="rounded-md border border-stone-300 px-3 py-1 disabled:opacity-40 dark:border-stone-700"
        >
          Next
        </button>
      </div>
    </div>
  );
}
