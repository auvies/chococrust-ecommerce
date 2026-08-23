"use client";

import Link from "next/link";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { DataTable, type Column } from "@/components/admin/ui/DataTable";
import { StatusBadge } from "@/components/admin/ui/StatusBadge";
import { useAdminList } from "@/lib/hooks/useAdminList";
import { getOrders } from "@/lib/api/admin/orders";
import { formatPrice } from "@/lib/format";
import { PERMISSIONS } from "@/lib/permissions";
import type { Order } from "@/types/api";

export default function OrdersPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.ordersView, PERMISSIONS.ordersManage]}>
      <OrdersManager />
    </RequirePermission>
  );
}

function OrdersManager() {
  const { data, loading } = useAdminList(() => getOrders({ sort: "-created_at", per_page: 50 }));

  const columns: Column<Order>[] = [
    { key: "number", header: "Order #", render: (o) => <Link href={`/admin/orders/${o.id}`} className="font-medium hover:underline">{o.order_number}</Link> },
    { key: "contact", header: "Contact", render: (o) => o.contact_name },
    { key: "status", header: "Status", render: (o) => <StatusBadge status={o.status} /> },
    { key: "total", header: "Total", render: (o) => formatPrice(o.total, o.currency) },
    { key: "type", header: "Delivery", render: (o) => o.delivery_type },
    {
      key: "actions",
      header: "",
      render: (o) => (
        <Link href={`/admin/orders/${o.id}`} className="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300">
          Manage
        </Link>
      ),
    },
  ];

  return (
    <div>
      <PageHeader title="Order Manager" />
      <DataTable columns={columns} rows={data} rowKey={(o) => o.id} loading={loading} emptyMessage="No orders yet." />
    </div>
  );
}
