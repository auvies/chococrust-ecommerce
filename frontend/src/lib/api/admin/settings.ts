import { adminFetch } from "@/lib/api/admin/client";
import type { ApiEnvelope, SystemSetting } from "@/types/api";

export async function getAllSettings(): Promise<SystemSetting[]> {
  const { data } = await adminFetch<ApiEnvelope<SystemSetting[]>>("/v1/settings");
  return data;
}

export async function upsertSetting(payload: {
  key: string;
  value: string;
  type: "string" | "number" | "boolean" | "json";
  group?: string | null;
  is_public?: boolean;
}): Promise<SystemSetting> {
  const { data } = await adminFetch<ApiEnvelope<SystemSetting>>("/v1/settings", { method: "PUT", body: payload });
  return data;
}
