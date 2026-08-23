"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { RequireCustomerAuth } from "@/components/account/RequireCustomerAuth";
import { OrderDetail } from "@/components/account/OrderDetail";
import { useAdminResource } from "@/lib/hooks/useAdminList";
import { getOrder } from "@/lib/api/orders";
import { Container } from "@/components/ui/Container";
import type { Order } from "@/types/api";

export default function OrderConfirmationPage() {
  return (
    <RequireCustomerAuth>
      <Confirmation />
    </RequireCustomerAuth>
  );
}

function Confirmation() {
  const params = useParams<{ id: string }>();
  const orderId = Number(params.id);

  const { data: order, loading, error } = useAdminResource<Order | null>(() => getOrder(orderId), null, orderId);

  return (
    <main className="flex-1 py-6 sm:py-10">
      <Container className="flex max-w-2xl flex-col gap-6">
        <div className="rounded-lg border border-green-200 bg-green-50 p-4 text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-400">
          <h1 className="text-xl font-semibold">Thank you — your order is confirmed!</h1>
          <p className="mt-1 text-sm">We&apos;ll keep you updated as it moves through preparation and delivery.</p>
        </div>

        {loading ? (
          <p className="text-sm text-stone-500">Loading your order…</p>
        ) : error || !order ? (
          <p className="text-sm text-red-600 dark:text-red-400">Couldn&apos;t load this order. It may not belong to your account.</p>
        ) : (
          <OrderDetail order={order} />
        )}

        <Link href="/account/orders" className="text-sm font-medium text-amber-800 hover:underline dark:text-amber-500">
          View all your orders
        </Link>
      </Container>
    </main>
  );
}
