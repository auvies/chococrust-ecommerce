"use client";

import Link from "next/link";
import { RequireCustomerAuth } from "@/components/account/RequireCustomerAuth";
import { AccountNav } from "@/components/account/AccountNav";
import { StatusBadge } from "@/components/admin/ui/StatusBadge";
import { useAdminList } from "@/lib/hooks/useAdminList";
import { getMyOrders } from "@/lib/api/orders";
import { formatPrice, formatDate } from "@/lib/format";
import { Container } from "@/components/ui/Container";
import { EmptyState } from "@/components/ui/EmptyState";
import type { Order } from "@/types/api";

export default function OrderHistoryPage() {
  return (
    <RequireCustomerAuth>
      <OrderHistory />
    </RequireCustomerAuth>
  );
}

function OrderHistory() {
  const { data: orders, loading } = useAdminList<Order>(() => getMyOrders({ sort: "-created_at", per_page: 20 }));

  return (
    <main className="flex-1 py-6 sm:py-10">
      <Container className="flex max-w-2xl flex-col gap-6">
        <AccountNav />
        <h1 className="text-2xl font-semibold tracking-tight text-stone-900 dark:text-stone-100">Your Orders</h1>

        {loading ? (
          <p className="text-sm text-stone-500">Loading…</p>
        ) : orders.length === 0 ? (
          <EmptyState message="You haven't placed any orders yet." />
        ) : (
          <div className="flex flex-col gap-3">
            {orders.map((order) => (
              <Link
                key={order.id}
                href={`/account/orders/${order.id}`}
                className="flex items-center justify-between gap-3 rounded-lg border border-stone-200 p-4 hover:border-amber-700 dark:border-stone-800 dark:hover:border-amber-600"
              >
                <div>
                  <p className="font-medium text-stone-900 dark:text-stone-100">{order.order_number}</p>
                  <p className="text-xs text-stone-500 dark:text-stone-400">
                    {order.placed_at ? formatDate(order.placed_at) : formatDate(order.created_at)} · {order.items.length} item(s)
                  </p>
                </div>
                <div className="flex flex-col items-end gap-1">
                  <StatusBadge status={order.status} />
                  <span className="text-sm font-medium text-stone-900 dark:text-stone-100">{formatPrice(order.total, order.currency)}</span>
                </div>
              </Link>
            ))}
          </div>
        )}
      </Container>
    </main>
  );
}
