import { StarRating } from "@/components/ui/StarRating";
import { EmptyState } from "@/components/ui/EmptyState";
import { formatDate } from "@/lib/format";
import type { Review } from "@/types/api";

export function ReviewList({
  reviews,
  total,
  ratingAverage,
}: {
  reviews: Review[];
  total: number;
  /** The true average across every approved review (server-aggregated) - not just the page of reviews loaded here. */
  ratingAverage?: number | null;
}) {
  if (reviews.length === 0) {
    return <EmptyState message="No reviews yet — be the first to review this product." />;
  }

  // Falls back to the partial-page average only if the server didn't supply
  // one (e.g. an older cached response) - the real figure always wins.
  const average = ratingAverage ?? reviews.reduce((sum, r) => sum + r.rating, 0) / reviews.length;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center gap-2">
        <StarRating rating={average} count={total} />
      </div>
      <ul className="flex flex-col divide-y divide-stone-200 dark:divide-stone-800">
        {reviews.map((review) => (
          <li key={review.id} className="flex flex-col gap-1 py-4">
            <div className="flex items-center justify-between gap-2">
              <StarRating rating={review.rating} />
              <span className="text-xs text-stone-500 dark:text-stone-500">{formatDate(review.created_at)}</span>
            </div>
            {review.title ? (
              <p className="text-sm font-semibold text-stone-900 dark:text-stone-100">{review.title}</p>
            ) : null}
            {review.body ? <p className="text-sm text-stone-600 dark:text-stone-400">{review.body}</p> : null}
            {review.is_verified_purchase ? (
              <span className="w-fit rounded bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/40 dark:text-green-400">
                Verified purchase
              </span>
            ) : null}
          </li>
        ))}
      </ul>
    </div>
  );
}
