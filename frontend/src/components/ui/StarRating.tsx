export function StarRating({ rating, count }: { rating: number; count?: number }) {
  const rounded = Math.round(rating);

  return (
    <div className="flex items-center gap-1" aria-label={`Rated ${rating} out of 5`}>
      <span className="text-amber-600" aria-hidden="true">
        {"★".repeat(rounded)}
        {"☆".repeat(5 - rounded)}
      </span>
      {count !== undefined ? (
        <span className="text-xs text-stone-500 dark:text-stone-400">({count})</span>
      ) : null}
    </div>
  );
}
