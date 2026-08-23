"use client";

import { useCallback, useEffect, useState } from "react";
import { ApiError } from "@/lib/api/client";
import { emptyPage } from "@/lib/api/query";
import type { Paginated } from "@/types/api";

/**
 * Shared list-loading state for every admin table: fetch, loading, error,
 * and a `refetch` every mutation calls afterward so the table reflects the
 * latest server state instead of guessing at an optimistic update.
 *
 * `dep` re-runs the fetch when it changes (e.g. a page number or filter) —
 * a single value rather than a dependency array, since the stricter
 * react-hooks lint rules require a literal array at every useCallback/
 * useEffect call site and can't statically verify a forwarded array.
 */
export function useAdminList<T>(loader: () => Promise<Paginated<T>>, dep?: unknown) {
  const [result, setResult] = useState<Paginated<T>>(() => emptyPage<T>());
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const refetch = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setResult(await loader());
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to load data.");
    } finally {
      setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [dep]);

  useEffect(() => {
    // refetch() sets `loading`/`error` synchronously before its first
    // await (unavoidable for any "loading" flag on a fetch-on-mount), which
    // this rule can't distinguish from a genuine cascading-render bug.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    refetch();
  }, [refetch]);

  return { data: result.data, meta: result.meta, loading, error, refetch };
}

/**
 * Same shape as `useAdminList` but for a single non-paginated resource (or
 * plain array) — a detail page's entity, a filtered sub-list, etc.
 */
export function useAdminResource<T>(loader: () => Promise<T>, initial: T, dep?: unknown) {
  const [data, setData] = useState<T>(initial);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const refetch = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setData(await loader());
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to load data.");
    } finally {
      setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [dep]);

  useEffect(() => {
    // refetch() sets `loading`/`error` synchronously before its first
    // await (unavoidable for any "loading" flag on a fetch-on-mount), which
    // this rule can't distinguish from a genuine cascading-render bug.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    refetch();
  }, [refetch]);

  return { data, loading, error, refetch };
}
