import { apiFetch } from "@/lib/api/client";
import type { AuthUser } from "@/types/api";

export interface LoginPayload {
  email: string;
  password: string;
}

/** Sets the access/refresh/CSRF cookies as a side effect (browser <-> backend direct call). */
export async function login(payload: LoginPayload): Promise<{ id: number; name: string; email: string }> {
  return apiFetch("/v1/auth/login", { method: "POST", body: payload });
}

export interface RegisterPayload {
  name: string;
  email: string;
  phone?: string;
  password: string;
  password_confirmation: string;
}

/** Also creates the `customer` role + Customer profile and logs the new account in immediately (see AuthController::register). */
export async function register(payload: RegisterPayload): Promise<{ id: number; name: string; email: string }> {
  return apiFetch("/v1/auth/register", { method: "POST", body: payload });
}

export async function logout(): Promise<void> {
  await apiFetch<void>("/v1/auth/logout", { method: "POST" });
}

/** The one source of truth for "who is logged in and what can they do" — never decode the JWT client-side. */
export async function me(): Promise<AuthUser> {
  return apiFetch<AuthUser>("/v1/auth/me");
}

export async function refreshSession(): Promise<void> {
  await apiFetch<void>("/v1/auth/refresh", { method: "POST" });
}
