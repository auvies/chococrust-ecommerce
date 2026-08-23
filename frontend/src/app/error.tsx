"use client";

import { useEffect } from "react";
import { logger } from "@/lib/logger";

/**
 * Route-segment error boundary. Shows a generic, human-readable message —
 * never a raw stack trace or internal error string (CLAUDE.md §16).
 */
export default function Error({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    logger.error("Unhandled route error", { message: error.message, digest: error.digest });
  }, [error]);

  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
      <h1 className="text-xl font-semibold">Something went wrong</h1>
      <p className="max-w-sm text-sm text-zinc-600 dark:text-zinc-400">
        Please try again. If the problem continues, contact support.
      </p>
      <button
        type="button"
        onClick={reset}
        className="rounded-full bg-foreground px-5 py-2 text-sm font-medium text-background"
      >
        Try again
      </button>
    </div>
  );
}
