"use client";

import { useState } from "react";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { DataTable, type Column } from "@/components/admin/ui/DataTable";
import { StatusBadge } from "@/components/admin/ui/StatusBadge";
import { FilterTabs, type FilterTab } from "@/components/admin/ui/FilterTabs";
import { Modal } from "@/components/admin/ui/Modal";
import { FormError } from "@/components/admin/ui/FormError";
import { SelectField, TextField } from "@/components/admin/ui/fields";
import { useAdminList } from "@/lib/hooks/useAdminList";
import { getDeliveries, updateDeliveryStatus } from "@/lib/api/admin/delivery";
import { ApiError } from "@/lib/api/client";
import { PERMISSIONS } from "@/lib/permissions";
import type { Delivery } from "@/types/api";

// Mirrors UpdateDeliveryStatusRequest's `in:...` rule — "pending" is the
// creation-only initial status and isn't a valid target here.
const STATUS_OPTIONS = ["assigned", "picked_up", "in_transit", "out_for_delivery", "delivered", "failed", "returned"];

// "Provide admin dashboard views for: Failed delivery, Returns" - filtered
// slices of the same deliveries table (distinct from the COD page's own
// Failed/Returns views, which are about cash, not the physical attempt).
type DeliveryView = "all" | "failed" | "returned";

const VIEWS: FilterTab<DeliveryView>[] = [
  { key: "all", label: "All" },
  { key: "failed", label: "Failed delivery" },
  { key: "returned", label: "Returns" },
];

const VIEW_STATUSES: Record<DeliveryView, string[] | undefined> = {
  all: undefined,
  failed: ["failed"],
  returned: ["returned"],
};

export default function DeliveryPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.deliveriesManageAll, PERMISSIONS.deliveriesViewOwn, PERMISSIONS.deliveriesManageOwn]}>
      <DeliveryManager />
    </RequirePermission>
  );
}

function DeliveryManager() {
  const [view, setView] = useState<DeliveryView>("all");
  const statuses = VIEW_STATUSES[view];

  const { data, loading, refetch } = useAdminList(
    () => getDeliveries({ filter: statuses ? { status: statuses } : {}, sort: "-created_at", per_page: 50 }),
    view,
  );
  const [updating, setUpdating] = useState<Delivery | null>(null);

  const columns: Column<Delivery>[] = [
    { key: "order", header: "Order ID", render: (d) => d.order_id },
    { key: "type", header: "Type", render: (d) => d.type },
    { key: "courier", header: "Courier/Rider", render: (d) => d.courier_name ?? d.rider_id ?? "—" },
    { key: "status", header: "Status", render: (d) => <StatusBadge status={d.status} /> },
    { key: "attempts", header: "Attempts", render: (d) => d.delivery_attempts },
    { key: "reason", header: "Failure reason", render: (d) => d.failure_reason ?? "—" },
    { key: "tracking", header: "Tracking #", render: (d) => d.tracking_number ?? "—" },
    {
      key: "actions",
      header: "",
      render: (d) => (
        <button type="button" onClick={() => setUpdating(d)} className="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300">
          Update status
        </button>
      ),
    },
  ];

  return (
    <div>
      <PageHeader title="Delivery Manager" />
      <FilterTabs tabs={VIEWS} active={view} onChange={setView} />
      <DataTable columns={columns} rows={data} rowKey={(d) => d.id} loading={loading} emptyMessage="No deliveries in this view." />

      {updating ? (
        <UpdateModal
          delivery={updating}
          onClose={() => setUpdating(null)}
          onUpdated={async () => {
            setUpdating(null);
            await refetch();
          }}
        />
      ) : null}
    </div>
  );
}

function UpdateModal({ delivery, onClose, onUpdated }: { delivery: Delivery; onClose: () => void; onUpdated: () => Promise<void> }) {
  const [status, setStatus] = useState(delivery.status);
  const [location, setLocation] = useState("");
  const [note, setNote] = useState("");
  const [failureReason, setFailureReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]> | undefined>();
  const [submitting, setSubmitting] = useState(false);

  const needsFailureReason = status === "failed" || status === "returned";

  async function handleSubmit() {
    setSubmitting(true);
    setError(null);
    setFieldErrors(undefined);
    try {
      await updateDeliveryStatus(delivery.id, status, location || undefined, note || undefined, failureReason || undefined);
      await onUpdated();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
        setFieldErrors(err.errors);
      } else {
        setError("Something went wrong.");
      }
      setSubmitting(false);
    }
  }

  return (
    <Modal title={`Update delivery for order ${delivery.order_id}`} onClose={onClose}>
      <FormError message={error} fieldErrors={fieldErrors} />
      <SelectField id="status" label="Status" value={status} onChange={setStatus} options={STATUS_OPTIONS.map((s) => ({ value: s, label: s }))} />
      {needsFailureReason ? (
        <TextField
          id="failure_reason"
          label="Failure reason"
          required
          value={failureReason}
          onChange={setFailureReason}
          placeholder="e.g. Customer refused, address not found, customer unavailable"
        />
      ) : null}
      <TextField id="location" label="Location" value={location} onChange={setLocation} />
      <TextField id="note" label="Note" value={note} onChange={setNote} />
      <div className="mt-4 flex justify-end gap-2">
        <button type="button" onClick={onClose} className="rounded-md border border-stone-300 px-4 py-2 text-sm dark:border-stone-700">
          Cancel
        </button>
        <button type="button" disabled={submitting} onClick={handleSubmit} className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700">
          {submitting ? "Saving…" : "Save"}
        </button>
      </div>
    </Modal>
  );
}
