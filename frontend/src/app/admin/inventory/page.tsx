"use client";

import { useState } from "react";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { DataTable, type Column } from "@/components/admin/ui/DataTable";
import { Modal } from "@/components/admin/ui/Modal";
import { FormError } from "@/components/admin/ui/FormError";
import { NumberField, TextField } from "@/components/admin/ui/fields";
import { useAdminList, useAdminResource } from "@/lib/hooks/useAdminList";
import { getInventory, adjustInventory, getInventoryHistory } from "@/lib/api/admin/inventory";
import { ApiError } from "@/lib/api/client";
import { PERMISSIONS } from "@/lib/permissions";
import type { Inventory, InventoryMovement } from "@/types/api";

export default function InventoryPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.inventoryManage, PERMISSIONS.productsView]}>
      <InventoryManager />
    </RequirePermission>
  );
}

function InventoryManager() {
  const { data, loading, refetch } = useAdminList(() => getInventory({ sort: "-updated_at", per_page: 50 }));
  const [adjusting, setAdjusting] = useState<Inventory | null>(null);
  const [viewingHistory, setViewingHistory] = useState<Inventory | null>(null);

  const columns: Column<Inventory>[] = [
    { key: "variant", header: "Variant ID", render: (i) => i.product_variant_id },
    { key: "location", header: "Location", render: (i) => i.location },
    { key: "on_hand", header: "On hand", render: (i) => i.quantity_on_hand },
    { key: "reserved", header: "Reserved", render: (i) => i.quantity_reserved ?? "—" },
    { key: "available", header: "Available", render: (i) => i.quantity_available ?? "—" },
    { key: "sold", header: "Sold", render: (i) => i.quantity_sold ?? "—" },
    { key: "reorder", header: "Reorder level", render: (i) => i.reorder_level ?? "—" },
    {
      key: "actions",
      header: "",
      render: (i) => (
        <div className="flex gap-2">
          <button type="button" onClick={() => setAdjusting(i)} className="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300">
            Adjust
          </button>
          <button type="button" onClick={() => setViewingHistory(i)} className="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300">
            History
          </button>
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader title="Inventory Manager" />
      <DataTable columns={columns} rows={data} rowKey={(i) => i.id} loading={loading} emptyMessage="No inventory records yet." />

      {adjusting ? (
        <AdjustModal
          record={adjusting}
          onClose={() => setAdjusting(null)}
          onAdjusted={async () => {
            setAdjusting(null);
            await refetch();
          }}
        />
      ) : null}

      {viewingHistory ? <HistoryModal record={viewingHistory} onClose={() => setViewingHistory(null)} /> : null}
    </div>
  );
}

function AdjustModal({ record, onClose, onAdjusted }: { record: Inventory; onClose: () => void; onAdjusted: () => Promise<void> }) {
  const [delta, setDelta] = useState<number | "">("");
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit() {
    if (delta === "" || delta === 0 || !reason) return;
    setSubmitting(true);
    setError(null);
    try {
      await adjustInventory(record.id, delta, reason);
      await onAdjusted();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Something went wrong.");
      setSubmitting(false);
    }
  }

  return (
    <Modal title={`Adjust stock (currently ${record.quantity_on_hand})`} onClose={onClose}>
      <FormError message={error} />
      <NumberField id="delta" label="Change (use a negative number to remove stock)" required value={delta} onChange={setDelta} />
      <TextField id="reason" label="Reason" required value={reason} onChange={setReason} placeholder="e.g. Stock count correction" />
      <div className="mt-4 flex justify-end gap-2">
        <button type="button" onClick={onClose} className="rounded-md border border-stone-300 px-4 py-2 text-sm dark:border-stone-700">
          Cancel
        </button>
        <button type="button" disabled={submitting} onClick={handleSubmit} className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700">
          {submitting ? "Saving…" : "Apply"}
        </button>
      </div>
    </Modal>
  );
}

const MOVEMENT_LABELS: Record<string, string> = {
  reservation_created: "Reserved",
  reservation_committed: "Reservation confirmed",
  reservation_released: "Reservation released",
  reservation_expired: "Reservation expired",
  sale_fulfilled: "Sold",
  manual_adjustment: "Manual adjustment",
};

/** "Inventory history" - the append-only movement ledger for this variant. */
function HistoryModal({ record, onClose }: { record: Inventory; onClose: () => void }) {
  const { data: movements, loading } = useAdminResource(
    async () => (await getInventoryHistory(record.id, { per_page: 50 })).data,
    [] as InventoryMovement[],
    record.id,
  );

  return (
    <Modal title={`Inventory history — variant #${record.product_variant_id}`} onClose={onClose}>
      {loading ? (
        <p className="text-sm text-stone-500 dark:text-stone-400">Loading…</p>
      ) : movements.length === 0 ? (
        <p className="text-sm text-stone-500 dark:text-stone-400">No movements recorded yet.</p>
      ) : (
        <ul className="flex max-h-96 flex-col gap-2 overflow-y-auto text-sm">
          {movements.map((m) => (
            <li key={m.id} className="border-b border-stone-100 pb-2 last:border-0 dark:border-stone-800">
              <div className="flex items-center justify-between">
                <span className="font-medium text-stone-900 dark:text-stone-100">{MOVEMENT_LABELS[m.type] ?? m.type}</span>
                <span className={m.quantity_delta < 0 ? "text-red-600 dark:text-red-400" : "text-green-700 dark:text-green-400"}>
                  {m.quantity_delta > 0 ? "+" : ""}
                  {m.quantity_delta}
                </span>
              </div>
              <p className="text-xs text-stone-500 dark:text-stone-500">
                {new Date(m.created_at).toLocaleString()}
                {m.order_id ? ` · Order #${m.order_id}` : ""}
                {m.reason ? ` · ${m.reason}` : ""}
              </p>
            </li>
          ))}
        </ul>
      )}
    </Modal>
  );
}
