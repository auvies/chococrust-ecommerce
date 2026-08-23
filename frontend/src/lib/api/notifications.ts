import { adminFetch } from "@/lib/api/admin/client";
import { toQueryString, type ListQueryParams } from "@/lib/api/query";
import type { AppNotification, ApiEnvelope, Paginated } from "@/types/api";

/** Always implicitly scoped to the logged-in user's own notifications - see NotificationController's own docblock. */
export async function getMyNotifications(params: ListQueryParams = {}): Promise<Paginated<AppNotification>> {
  return adminFetch<Paginated<AppNotification>>(`/v1/notifications${toQueryString(params)}`);
}

export async function markNotificationRead(id: string): Promise<AppNotification> {
  const { data } = await adminFetch<ApiEnvelope<AppNotification>>(`/v1/notifications/${id}/read`, { method: "PATCH" });
  return data;
}

export async function markAllNotificationsRead(): Promise<void> {
  await adminFetch<void>("/v1/notifications/read-all", { method: "POST" });
}
