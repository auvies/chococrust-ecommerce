"use client";

import { useState } from "react";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { DataTable, type Column } from "@/components/admin/ui/DataTable";
import { StatusBadge } from "@/components/admin/ui/StatusBadge";
import { FilterTabs, type FilterTab } from "@/components/admin/ui/FilterTabs";
import { ConfirmButton } from "@/components/admin/ui/ConfirmButton";
import { Modal } from "@/components/admin/ui/Modal";
import { FormError } from "@/components/admin/ui/FormError";
import { TextField } from "@/components/admin/ui/fields";
import { useAdminAuth } from "@/components/admin/AuthProvider";
import { useAdminList } from "@/lib/hooks/useAdminList";
import { getCodRecords, collectCodRecord, verifyCodRecord, failCodRecord, returnCodRecord } from "@/lib/api/admin/payments";
import { formatPrice } from "@/lib/format";
import { ApiError } from "@/lib/api/client";
import { PERMISSIONS } from "@/lib/permissions";
import type { CodRecord } from "@/types/api";

// "Provide admin dashboard views for: COD orders, Delivered COD, Failed
// delivery, Returns" - filtered slices of the same cod_records table.
type CodView = "all" | "awaiting" | "delivered" | "failed" | "returned";

const VIEWS: FilterTab<CodView>[] = [
  { key: "all", label: "All" },
  { key: "awaiting", label: "COD Orders" },
  { key: "delivered", label: "Delivered COD" },
  { key: "failed", label: "Failed" },
  { key: "returned", label: "Returns" },
];

const VIEW_STATUSES: Record<CodView, string[] | undefined> = {
  all: undefined,
  awaiting: ["awaiting_delivery"],
  delivered: ["collected", "deposited"],
  failed: ["failed_collection"],
  returned: ["returned"],
};

export default function CodPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.codManage, PERMISSIONS.codCollect]}>
      <CodManager />
    </RequirePermission>
  );
}

function CodManager() {
  const { hasPermission } = useAdminAuth();
  const canManage = hasPermission(PERMISSIONS.codManage);
  const canCollect = hasPermission(PERMISSIONS.codCollect);
  const [view, setView] = useState<CodView>("all");
  const statuses = VIEW_STATUSES[view];

  const { data, loading, refetch } = useAdminList(
    () => getCodRecords({ filter: statuses ? { status: statuses } : {}, sort: "-created_at", per_page: 50 }),
    view,
  );
  const [failing, setFailing] = useState<CodRecord | null>(null);

  const columns: Column<CodRecord>[] = [
    { key: "order", header: "Order ID", render: (c) => c.order_id },
    { key: "status", header: "Status", render: (c) => <StatusBadge status={c.status} /> },
    { key: "due", header: "Amount due", render: (c) => formatPrice(c.amount_due, "PKR") },
    { key: "collected", header: "Collected", render: (c) => (c.amount_collected != null ? formatPrice(c.amount_collected, "PKR") : "—") },
    {
      key: "actions",
      header: "",
      render: (c) => (
        <div className="flex flex-wrap gap-2">
          {canCollect && c.status === "awaiting_delivery" ? (
            <ConfirmButton
              label="Mark collected"
              confirmMessage={`Confirm cash collected for order ${c.order_id}?`}
              onConfirm={async () => {
                await collectCodRecord(c.id, c.amount_due);
                await refetch();
              }}
            />
          ) : null}
          {canManage && c.status === "collected" ? (
            <ConfirmButton
              label="Verify & deposit"
              confirmMessage="Confirm this cash has been deposited?"
              onConfirm={async () => {
                await verifyCodRecord(c.id);
                await refetch();
              }}
            />
          ) : null}
          {(canManage || canCollect) && c.status === "awaiting_delivery" ? (
            <button
              type="button"
              onClick={() => setFailing(c)}
              className="rounded-md bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-100 dark:bg-red-950 dark:text-red-400"
            >
              Mark failed
            </button>
          ) : null}
          {(canManage || canCollect) && ["awaiting_delivery", "failed_collection"].includes(c.status) ? (
            <ConfirmButton
              label="Return to seller"
              variant="danger"
              confirmMessage={`Confirm order ${c.order_id} is being returned to seller?`}
              onConfirm={async () => {
                await returnCodRecord(c.id);
                await refetch();
              }}
            />
          ) : null}
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader title="COD Manager" />
      <FilterTabs tabs={VIEWS} active={view} onChange={setView} />
      <DataTable columns={columns} rows={data} rowKey={(c) => c.id} loading={loading} emptyMessage="No COD records in this view." />

      {failing ? (
        <FailModal
          record={failing}
          onClose={() => setFailing(null)}
          onFailed={async () => {
            setFailing(null);
            await refetch();
          }}
        />
      ) : null}
    </div>
  );
}

function FailModal({ record, onClose, onFailed }: { record: CodRecord; onClose: () => void; onFailed: () => Promise<void> }) {
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit() {
    if (!reason) return;
    setSubmitting(true);
    setError(null);
    try {
      await failCodRecord(record.id, reason);
      await onFailed();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Something went wrong.");
      setSubmitting(false);
    }
  }

  return (
    <Modal title={`Mark delivery failed for order ${record.order_id}`} onClose={onClose}>
      <FormError message={error} />
      <TextField id="reason" label="Reason" required value={reason} onChange={setReason} placeholder="e.g. Customer unreachable, address not found" />
      <div className="mt-4 flex justify-end gap-2">
        <button type="button" onClick={onClose} className="rounded-md border border-stone-300 px-4 py-2 text-sm dark:border-stone-700">
          Cancel
        </button>
        <button type="button" disabled={submitting} onClick={handleSubmit} className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700">
          {submitting ? "Saving…" : "Mark failed"}
        </button>
      </div>
    </Modal>
  );
}
