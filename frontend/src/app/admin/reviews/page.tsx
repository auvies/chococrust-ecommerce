"use client";

import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { DataTable, type Column } from "@/components/admin/ui/DataTable";
import { StatusBadge } from "@/components/admin/ui/StatusBadge";
import { ConfirmButton } from "@/components/admin/ui/ConfirmButton";
import { useAdminList } from "@/lib/hooks/useAdminList";
import { getAllReviews, approveReview, rejectReview } from "@/lib/api/admin/reviews";
import { formatDate } from "@/lib/format";
import { PERMISSIONS } from "@/lib/permissions";
import type { Review } from "@/types/api";

export default function ReviewsPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.reviewsModerate]}>
      <ReviewsManager />
    </RequirePermission>
  );
}

function ReviewsManager() {
  const { data, loading, refetch } = useAdminList(() => getAllReviews({ sort: "-created_at", per_page: 50 }));

  const columns: Column<Review>[] = [
    { key: "product", header: "Product ID", render: (r) => r.product_id },
    { key: "rating", header: "Rating", render: (r) => "★".repeat(r.rating) },
    { key: "title", header: "Title", render: (r) => r.title ?? "—" },
    { key: "body", header: "Body", render: (r) => <span className="line-clamp-2 block max-w-xs">{r.body ?? "—"}</span> },
    { key: "status", header: "Status", render: (r) => <StatusBadge status={r.is_approved ? "approved" : "pending"} /> },
    { key: "verified", header: "Verified purchase", render: (r) => (r.is_verified_purchase ? "Yes" : "No") },
    { key: "date", header: "Date", render: (r) => formatDate(r.created_at) },
    {
      key: "actions",
      header: "",
      render: (r) => (
        <div className="flex gap-2">
          {!r.is_approved ? (
            <ConfirmButton
              label="Approve"
              onConfirm={async () => {
                await approveReview(r.id);
                await refetch();
              }}
            />
          ) : null}
          <ConfirmButton
            label="Reject"
            variant="danger"
            confirmMessage="Reject and permanently remove this review?"
            onConfirm={async () => {
              await rejectReview(r.id);
              await refetch();
            }}
          />
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader title="Review Manager" />
      <DataTable columns={columns} rows={data} rowKey={(r) => r.id} loading={loading} emptyMessage="No reviews yet." />
    </div>
  );
}
