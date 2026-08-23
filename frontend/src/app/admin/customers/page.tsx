"use client";

import Link from "next/link";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { DataTable, type Column } from "@/components/admin/ui/DataTable";
import { useAdminList } from "@/lib/hooks/useAdminList";
import { getCustomers } from "@/lib/api/admin/customers";
import { formatDate } from "@/lib/format";
import { PERMISSIONS } from "@/lib/permissions";
import type { Customer } from "@/types/api";

export default function CustomersPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.customersView, PERMISSIONS.customersManage]}>
      <CustomersManager />
    </RequirePermission>
  );
}

function CustomersManager() {
  const { data, loading } = useAdminList(() => getCustomers({ sort: "-created_at", per_page: 50 }));

  const columns: Column<Customer>[] = [
    { key: "name", header: "Name", render: (c) => <Link href={`/admin/customers/${c.id}`} className="font-medium hover:underline">{c.name ?? "—"}</Link> },
    { key: "email", header: "Email", render: (c) => c.email ?? "—" },
    { key: "phone", header: "Phone", render: (c) => c.phone ?? "—" },
    { key: "since", header: "Customer since", render: (c) => formatDate(c.created_at) },
    {
      key: "actions",
      header: "",
      render: (c) => (
        <Link href={`/admin/customers/${c.id}`} className="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300">
          View
        </Link>
      ),
    },
  ];

  return (
    <div>
      <PageHeader title="Customer Manager" />
      <DataTable columns={columns} rows={data} rowKey={(c) => c.id} loading={loading} emptyMessage="No customers yet." />
    </div>
  );
}
