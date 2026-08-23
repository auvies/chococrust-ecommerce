import Link from "next/link";
import { StarRating } from "@/components/ui/StarRating";
import { EmptyState } from "@/components/ui/EmptyState";
import { formatDate } from "@/lib/format";
import { productHref } from "@/lib/routes";
import type { Testimonial } from "@/lib/homepage";

export function Testimonials({ testimonials }: { testimonials: Testimonial[] }) {
  if (testimonials.length === 0) {
    return <EmptyState message="No customer reviews yet — be the first to leave one." />;
  }

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {testimonials.map((review) => (
        <figure
          key={review.id}
          className="flex flex-col gap-2 rounded-xl border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900"
        >
          <StarRating rating={review.rating} />
          {review.title ? (
            <figcaption className="text-sm font-semibold text-stone-900 dark:text-stone-100">
              {review.title}
            </figcaption>
          ) : null}
          {review.body ? (
            <blockquote className="line-clamp-4 text-sm text-stone-600 dark:text-stone-400">
              &ldquo;{review.body}&rdquo;
            </blockquote>
          ) : null}
          <div className="mt-auto flex items-center justify-between pt-2 text-xs text-stone-500 dark:text-stone-500">
            <Link
              href={productHref({ id: review.productId, slug: review.productSlug })}
              className="hover:underline"
            >
              {review.productName}
            </Link>
            <span>{formatDate(review.created_at)}</span>
          </div>
        </figure>
      ))}
    </div>
  );
}
