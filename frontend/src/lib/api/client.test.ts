import { afterEach, describe, expect, it, vi } from "vitest";
import { apiFetch, ApiError } from "./client";

describe("apiFetch", () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    document.cookie = "cc_csrf_token=; expires=Thu, 01 Jan 1970 00:00:00 GMT";
  });

  it("returns parsed JSON on a successful response", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ status: "ok" }),
    });
    vi.stubGlobal("fetch", fetchMock);

    const result = await apiFetch<{ status: string }>("/v1/health");

    expect(result).toEqual({ status: "ok" });
    expect(fetchMock).toHaveBeenCalledWith(
      "http://localhost:8000/v1/health",
      expect.objectContaining({ headers: expect.objectContaining({ Accept: "application/json" }) }),
    );
  });

  it("throws ApiError using the backend's own sanitized message on failure", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: false,
      status: 500,
      json: async () => ({ message: "boom" }),
    });
    vi.stubGlobal("fetch", fetchMock);

    await expect(apiFetch("/v1/health")).rejects.toMatchObject(new ApiError("boom", 500));
  });

  it("falls back to a generic message when the error body isn't JSON", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: false,
      status: 502,
      json: async () => {
        throw new Error("not json");
      },
    });
    vi.stubGlobal("fetch", fetchMock);

    await expect(apiFetch("/v1/health")).rejects.toMatchObject(
      new ApiError("Request to /v1/health failed", 502),
    );
  });

  it("surfaces per-field validation errors from a 422 response", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: false,
      status: 422,
      json: async () => ({ message: "The given data was invalid.", errors: { name: ["The name field is required."] } }),
    });
    vi.stubGlobal("fetch", fetchMock);

    await expect(apiFetch("/v1/categories", { method: "POST", body: {} })).rejects.toMatchObject({
      status: 422,
      errors: { name: ["The name field is required."] },
    });
  });

  it("sends credentials and echoes the CSRF cookie as a header on mutating requests", async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200, json: async () => ({}) });
    vi.stubGlobal("fetch", fetchMock);
    document.cookie = "cc_csrf_token=abc123";

    await apiFetch("/v1/categories/1", { method: "PUT", body: { name: "Cakes" } });

    expect(fetchMock).toHaveBeenCalledWith(
      "http://localhost:8000/v1/categories/1",
      expect.objectContaining({
        credentials: "include",
        headers: expect.objectContaining({ "X-CSRF-Token": "abc123" }),
      }),
    );
  });
});
