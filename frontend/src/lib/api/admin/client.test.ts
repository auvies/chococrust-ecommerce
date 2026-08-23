import { afterEach, describe, expect, it, vi } from "vitest";
import { adminFetch } from "./client";

describe("adminFetch", () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    vi.resetModules();
  });

  it("returns the response directly on success, without attempting a refresh", async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200, json: async () => ({ status: "ok" }) });
    vi.stubGlobal("fetch", fetchMock);

    const result = await adminFetch<{ status: string }>("/v1/orders");

    expect(result).toEqual({ status: "ok" });
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it("on a 401, refreshes the session once and retries the original request", async () => {
    const fetchMock = vi
      .fn()
      // 1) original request fails with 401
      .mockResolvedValueOnce({ ok: false, status: 401, json: async () => ({ message: "Unauthenticated." }) })
      // 2) refresh succeeds
      .mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({ status: "refreshed" }) })
      // 3) retried original request succeeds
      .mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({ status: "ok" }) });
    vi.stubGlobal("fetch", fetchMock);

    const result = await adminFetch<{ status: string }>("/v1/orders");

    expect(result).toEqual({ status: "ok" });
    expect(fetchMock).toHaveBeenCalledTimes(3);
    expect(fetchMock.mock.calls[1][0]).toContain("/v1/auth/refresh");
  });

  it("propagates the error if the refresh attempt itself fails (session is genuinely gone)", async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: false, status: 401, json: async () => ({ message: "Unauthenticated." }) })
      .mockResolvedValueOnce({ ok: false, status: 401, json: async () => ({ message: "Unauthenticated." }) });
    vi.stubGlobal("fetch", fetchMock);

    await expect(adminFetch("/v1/orders")).rejects.toMatchObject({ status: 401 });
    expect(fetchMock).toHaveBeenCalledTimes(2);
  });

  it("does not attempt a refresh loop when the auth endpoint itself 401s", async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: false, status: 401, json: async () => ({ message: "Unauthenticated." }) });
    vi.stubGlobal("fetch", fetchMock);

    await expect(adminFetch("/v1/auth/me")).rejects.toMatchObject({ status: 401 });
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });
});
