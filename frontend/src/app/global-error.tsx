"use client";

import { useEffect } from "react";
import { logger } from "@/lib/logger";

/**
 * Root-level error boundary — catches failures in the root layout itself.
 * Must render its own <html>/<body> since it replaces the root layout.
 */
export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    logger.error("Unhandled global error", { message: error.message, digest: error.digest });
  }, [error]);

  return (
    <html lang="en">
      <body>
        <div className="flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center">
          <h1 className="text-xl font-semibold">Something went wrong</h1>
          <p className="max-w-sm text-sm text-zinc-600">
            Please try again. If the problem continues, contact support.
          </p>
          <button
            type="button"
            onClick={reset}
            className="rounded-full bg-black px-5 py-2 text-sm font-medium text-white"
          >
            Try again
          </button>
        </div>
      </body>
    </html>
  );
}
