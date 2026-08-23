"use client";

import { useState } from "react";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { DataTable, type Column } from "@/components/admin/ui/DataTable";
import { StatusBadge } from "@/components/admin/ui/StatusBadge";
import { FilterTabs, type FilterTab } from "@/components/admin/ui/FilterTabs";
import { Modal } from "@/components/admin/ui/Modal";
import { FormError } from "@/components/admin/ui/FormError";
import { NumberField, TextField } from "@/components/admin/ui/fields";
import { useAdminAuth } from "@/components/admin/AuthProvider";
import { useAdminList } from "@/lib/hooks/useAdminList";
import { getPayments, refundPayment } from "@/lib/api/admin/payments";
import { formatPrice } from "@/lib/format";
import { ApiError } from "@/lib/api/client";
import { PERMISSIONS } from "@/lib/permissions";
import type { Payment } from "@/types/api";

// "Provide admin dashboard views for: Paid orders, Pending payments, Failed
// payments" - one table, filtered server-side by `payments.status`, rather
// than three separate pages for what's the same resource with a different
// WHERE clause.
type PaymentView = "all" | "paid" | "pending" | "failed";

const VIEWS: FilterTab<PaymentView>[] = [
  { key: "all", label: "All" },
  { key: "paid", label: "Paid" },
  { key: "pending", label: "Pending" },
  { key: "failed", label: "Failed" },
];

/** Mirrors config('payments.statuses') groupings a "Pending" or "Failed" view should cover. */
const VIEW_STATUSES: Record<PaymentView, string[] | undefined> = {
  all: undefined,
  paid: ["paid"],
  pending: ["pending", "cod_pending", "cod_confirmed"],
  failed: ["failed", "cancelled"],
};

export default function PaymentsPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.paymentsView, PERMISSIONS.paymentsManage]}>
      <PaymentsManager />
    </RequirePermission>
  );
}

function PaymentsManager() {
  const { hasPermission } = useAdminAuth();
  const canRefund = hasPermission(PERMISSIONS.ordersRefund);
  const [view, setView] = useState<PaymentView>("all");
  const statuses = VIEW_STATUSES[view];

  const { data, loading, refetch } = useAdminList(
    () => getPayments({ filter: statuses ? { status: statuses } : {}, sort: "-created_at", per_page: 50 }),
    view,
  );
  const [refunding, setRefunding] = useState<Payment | null>(null);

  const columns: Column<Payment>[] = [
    { key: "order", header: "Order ID", render: (p) => p.order_id },
    { key: "method", header: "Method", render: (p) => p.method },
    { key: "status", header: "Status", render: (p) => <StatusBadge status={p.status} /> },
    { key: "amount", header: "Amount", render: (p) => formatPrice(p.amount, p.currency) },
    { key: "refunded", header: "Refunded", render: (p) => (p.refunded_total ? formatPrice(p.refunded_total, p.currency) : "—") },
    {
      key: "actions",
      header: "",
      render: (p) =>
        canRefund && ["paid", "partially_refunded"].includes(p.status) ? (
          <button type="button" onClick={() => setRefunding(p)} className="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300">
            Refund
          </button>
        ) : null,
    },
  ];

  return (
    <div>
      <PageHeader title="Payment Manager" />
      <FilterTabs tabs={VIEWS} active={view} onChange={setView} />
      <DataTable columns={columns} rows={data} rowKey={(p) => p.id} loading={loading} emptyMessage="No payments in this view." />

      {refunding ? (
        <RefundModal
          payment={refunding}
          onClose={() => setRefunding(null)}
          onRefunded={async () => {
            setRefunding(null);
            await refetch();
          }}
        />
      ) : null}
    </div>
  );
}

function RefundModal({ payment, onClose, onRefunded }: { payment: Payment; onClose: () => void; onRefunded: () => Promise<void> }) {
  const remaining = payment.amount - (payment.refunded_total ?? 0);
  const [amount, setAmount] = useState<number | "">(remaining);
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit() {
    if (amount === "" || !reason) return;
    setSubmitting(true);
    setError(null);
    try {
      await refundPayment(payment.id, amount, reason);
      await onRefunded();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Something went wrong.");
      setSubmitting(false);
    }
  }

  return (
    <Modal title={`Refund payment #${payment.id} (up to ${formatPrice(remaining, payment.currency)})`} onClose={onClose}>
      <FormError message={error} />
      <NumberField id="amount" label="Refund amount" required value={amount} onChange={setAmount} min={0} />
      <TextField id="reason" label="Reason" required value={reason} onChange={setReason} />
      <div className="mt-4 flex justify-end gap-2">
        <button type="button" onClick={onClose} className="rounded-md border border-stone-300 px-4 py-2 text-sm dark:border-stone-700">
          Cancel
        </button>
        <button type="button" disabled={submitting} onClick={handleSubmit} className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700">
          {submitting ? "Refunding…" : "Refund"}
        </button>
      </div>
    </Modal>
  );
}
