import { OfferCard } from "@/components/product/OfferCard";
import { EmptyState } from "@/components/ui/EmptyState";
import type { Offer } from "@/lib/homepage";

export function OffersGrid({ offers }: { offers: Offer[] }) {
  if (offers.length === 0) {
    return <EmptyState message="No active offers right now — check back soon." />;
  }

  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
      {offers.map((offer) => (
        <OfferCard key={offer.product.id} offer={offer} />
      ))}
    </div>
  );
}
