"use client";

import { useState } from "react";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { DataTable, type Column } from "@/components/admin/ui/DataTable";
import { AdminPagination } from "@/components/admin/ui/AdminPagination";
import { useAdminList } from "@/lib/hooks/useAdminList";
import { getAuditLogs } from "@/lib/api/admin/auditLogs";
import { formatDate } from "@/lib/format";
import { PERMISSIONS } from "@/lib/permissions";
import type { AuditLog } from "@/types/api";

export default function AuditLogsPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.auditView]}>
      <AuditLogsManager />
    </RequirePermission>
  );
}

function AuditLogsManager() {
  const [page, setPage] = useState(1);
  const { data, meta, loading } = useAdminList(() => getAuditLogs({ sort: "-created_at", page, per_page: 30 }), page);

  const columns: Column<AuditLog>[] = [
    { key: "date", header: "Date", render: (l) => formatDate(l.created_at) },
    { key: "action", header: "Action", render: (l) => l.action },
    { key: "user", header: "User ID", render: (l) => l.user_id ?? "system" },
    { key: "subject", header: "Subject", render: (l) => (l.auditable_type ? `${l.auditable_type} #${l.auditable_id}` : "—") },
    { key: "ip", header: "IP", render: (l) => l.ip_address ?? "—" },
  ];

  return (
    <div>
      <PageHeader title="Audit Logs" />
      <DataTable columns={columns} rows={data} rowKey={(l) => l.id} loading={loading} emptyMessage="No audit events yet." />
      <AdminPagination meta={meta} page={page} onPageChange={setPage} />
    </div>
  );
}
