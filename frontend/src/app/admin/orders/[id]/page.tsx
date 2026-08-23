"use client";

import { useState } from "react";
import { useParams } from "next/navigation";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { FormError } from "@/components/admin/ui/FormError";
import { StatusBadge } from "@/components/admin/ui/StatusBadge";
import { ConfirmButton } from "@/components/admin/ui/ConfirmButton";
import { SelectField } from "@/components/admin/ui/fields";
import { useAdminAuth } from "@/components/admin/AuthProvider";
import { useAdminResource } from "@/lib/hooks/useAdminList";
import { getOrder, updateOrderStatus, cancelOrder, ORDER_TRANSITIONS } from "@/lib/api/admin/orders";
import { formatDate, formatPrice } from "@/lib/format";
import { ApiError } from "@/lib/api/client";
import { PERMISSIONS } from "@/lib/permissions";
import type { Order, OrderStatus } from "@/types/api";

export default function OrderDetailPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.ordersView, PERMISSIONS.ordersManage]}>
      <OrderDetail />
    </RequirePermission>
  );
}

function OrderDetail() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const { hasPermission } = useAdminAuth();
  const canManage = hasPermission(PERMISSIONS.ordersManage);

  const { data: order, loading, refetch: load } = useAdminResource<Order | null>(() => getOrder(id), null, id);
  const [nextStatus, setNextStatus] = useState<OrderStatus | "">("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  if (loading || !order) return <p className="text-sm text-stone-500">Loading…</p>;

  const transitions = ORDER_TRANSITIONS[order.status] ?? [];

  async function handleTransition() {
    if (!nextStatus) return;
    setSubmitting(true);
    setError(null);
    try {
      await updateOrderStatus(id, nextStatus);
      setNextStatus("");
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Something went wrong.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={`Order ${order.order_number}`} action={<StatusBadge status={order.status} />} />

      <section className="grid max-w-2xl grid-cols-2 gap-4 rounded-lg border border-stone-200 p-4 text-sm dark:border-stone-800">
        <div>
          <p className="text-stone-500">Contact</p>
          <p className="font-medium">{order.contact_name}</p>
          <p>{order.contact_phone}</p>
        </div>
        <div>
          <p className="text-stone-500">Total</p>
          <p className="font-medium">{formatPrice(order.total, order.currency)}</p>
          <p className="text-stone-500">Delivery: {order.delivery_type}</p>
        </div>
      </section>

      {canManage && (transitions.length > 0 || order.status === "pending" || order.status === "confirmed" || order.status === "processing") ? (
        <section className="max-w-md rounded-lg border border-stone-200 p-4 dark:border-stone-800">
          <h2 className="mb-3 text-sm font-semibold text-stone-900 dark:text-stone-100">Update status</h2>
          <FormError message={error} />
          {transitions.length > 0 ? (
            <div className="flex items-end gap-3">
              <div className="flex-1">
                <SelectField
                  id="next_status"
                  label="Next status"
                  value={nextStatus}
                  onChange={setNextStatus}
                  options={[{ value: "", label: "Select…" }, ...transitions.map((s) => ({ value: s, label: s }))]}
                />
              </div>
              <button
                type="button"
                onClick={handleTransition}
                disabled={submitting || !nextStatus}
                className="mb-3 rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700"
              >
                {submitting ? "Updating…" : "Apply"}
              </button>
            </div>
          ) : null}
          {["pending", "confirmed", "processing"].includes(order.status) ? (
            <ConfirmButton
              label="Cancel order"
              variant="danger"
              confirmMessage="Cancel this order? Any reserved stock will be released."
              onConfirm={async () => {
                await cancelOrder(id);
                await load();
              }}
            />
          ) : null}
        </section>
      ) : null}

      <section>
        <h2 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">Items</h2>
        <div className="overflow-x-auto rounded-lg border border-stone-200 dark:border-stone-800">
          <table className="w-full min-w-max text-left text-sm">
            <thead className="bg-stone-100 dark:bg-stone-900">
              <tr>
                <th className="px-3 py-2">Product</th>
                <th className="px-3 py-2">SKU</th>
                <th className="px-3 py-2">Qty</th>
                <th className="px-3 py-2">Unit price</th>
                <th className="px-3 py-2">Line total</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-stone-200 dark:divide-stone-800">
              {order.items.map((item) => (
                <tr key={item.id} className="bg-white dark:bg-stone-950">
                  <td className="px-3 py-2">{item.product_name} {item.variant_name ? `(${item.variant_name})` : ""}</td>
                  <td className="px-3 py-2">{item.sku}</td>
                  <td className="px-3 py-2">{item.quantity}</td>
                  <td className="px-3 py-2">{formatPrice(item.unit_price, order.currency)}</td>
                  <td className="px-3 py-2">{formatPrice(item.line_total, order.currency)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <section>
        <h2 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">Status history</h2>
        <ul className="flex flex-col gap-1 text-sm text-stone-700 dark:text-stone-300">
          {order.status_history.map((entry, i) => (
            <li key={i}>
              {entry.from_status ? `${entry.from_status} → ${entry.to_status}` : entry.to_status} — {formatDate(entry.created_at)}
              {entry.note ? ` — ${entry.note}` : ""}
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}
