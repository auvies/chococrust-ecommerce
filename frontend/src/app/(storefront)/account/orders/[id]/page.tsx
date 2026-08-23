"use client";

import { useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { RequireCustomerAuth } from "@/components/account/RequireCustomerAuth";
import { AccountNav } from "@/components/account/AccountNav";
import { OrderDetail } from "@/components/account/OrderDetail";
import { ConfirmButton } from "@/components/admin/ui/ConfirmButton";
import { useAdminResource } from "@/lib/hooks/useAdminList";
import { getOrder, cancelOrder } from "@/lib/api/orders";
import { Container } from "@/components/ui/Container";
import type { Order } from "@/types/api";

const CANCELLABLE_STATUSES = new Set(["pending", "confirmed", "processing"]);

export default function OrderDetailPage() {
  return (
    <RequireCustomerAuth>
      <OrderDetailView />
    </RequireCustomerAuth>
  );
}

function OrderDetailView() {
  const params = useParams<{ id: string }>();
  const orderId = Number(params.id);
  const [cancelling, setCancelling] = useState(false);

  const { data: order, loading, error, refetch } = useAdminResource<Order | null>(() => getOrder(orderId), null, orderId);

  return (
    <main className="flex-1 py-6 sm:py-10">
      <Container className="flex max-w-2xl flex-col gap-6">
        <AccountNav />
        <Link href="/account/orders" className="text-sm font-medium text-amber-800 hover:underline dark:text-amber-500">
          ← Back to your orders
        </Link>

        {loading ? (
          <p className="text-sm text-stone-500">Loading…</p>
        ) : error || !order ? (
          <p className="text-sm text-red-600 dark:text-red-400">Couldn&apos;t load this order.</p>
        ) : (
          <>
            <OrderDetail order={order} />

            {CANCELLABLE_STATUSES.has(order.status) ? (
              <div className="flex justify-end">
                <ConfirmButton
                  label={cancelling ? "Cancelling…" : "Cancel this order"}
                  variant="danger"
                  confirmMessage="Cancel this order? This can't be undone."
                  onConfirm={async () => {
                    setCancelling(true);
                    try {
                      await cancelOrder(order.id);
                      await refetch();
                    } finally {
                      setCancelling(false);
                    }
                  }}
                />
              </div>
            ) : null}
          </>
        )}
      </Container>
    </main>
  );
}
