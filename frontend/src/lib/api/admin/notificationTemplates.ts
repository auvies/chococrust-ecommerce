import { adminFetch } from "@/lib/api/admin/client";
import type { ApiEnvelope, NotificationTemplate } from "@/types/api";

export async function getNotificationTemplates(): Promise<NotificationTemplate[]> {
  const { data } = await adminFetch<ApiEnvelope<NotificationTemplate[]>>("/v1/notification-templates");
  return data;
}

export async function updateNotificationTemplate(
  id: number,
  changes: Partial<Pick<NotificationTemplate, "subject" | "body" | "is_active">>,
): Promise<NotificationTemplate> {
  const { data } = await adminFetch<ApiEnvelope<NotificationTemplate>>(`/v1/notification-templates/${id}`, {
    method: "PATCH",
    body: changes,
  });
  return data;
}
