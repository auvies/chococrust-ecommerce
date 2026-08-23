import { adminFetch } from "@/lib/api/admin/client";
import { toQueryString, type ListQueryParams } from "@/lib/api/query";
import type { AuditLog, Paginated } from "@/types/api";

export async function getAuditLogs(params: ListQueryParams = {}): Promise<Paginated<AuditLog>> {
  return adminFetch<Paginated<AuditLog>>(`/v1/audit-logs${toQueryString(params)}`);
}
