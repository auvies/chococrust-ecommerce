export interface FilterTab<T extends string> {
  key: T;
  label: string;
}

/** A row of filter chips for the admin dashboard "views" (e.g. Paid / Pending / Failed) — purely a controlled selector, the caller owns the actual filtering. */
export function FilterTabs<T extends string>({
  tabs,
  active,
  onChange,
}: {
  tabs: FilterTab<T>[];
  active: T;
  onChange: (key: T) => void;
}) {
  return (
    <div className="mb-4 flex flex-wrap gap-2" role="tablist">
      {tabs.map((tab) => (
        <button
          key={tab.key}
          type="button"
          role="tab"
          aria-selected={tab.key === active}
          onClick={() => onChange(tab.key)}
          className={`rounded-full px-3 py-1.5 text-xs font-medium ${
            tab.key === active
              ? "bg-stone-900 text-white dark:bg-amber-700"
              : "bg-stone-100 text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300 dark:hover:bg-stone-700"
          }`}
        >
          {tab.label}
        </button>
      ))}
    </div>
  );
}
