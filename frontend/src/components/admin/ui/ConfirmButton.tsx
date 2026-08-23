"use client";

import { useState } from "react";
import { ApiError } from "@/lib/api/client";

/** One-click admin action (delete/approve/refund/etc.) with a confirm prompt, pending state, and inline error display. */
export function ConfirmButton({
  label,
  confirmMessage,
  onConfirm,
  variant = "default",
}: {
  label: string;
  confirmMessage?: string;
  onConfirm: () => Promise<void>;
  variant?: "default" | "danger";
}) {
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleClick() {
    if (confirmMessage && !window.confirm(confirmMessage)) return;
    setPending(true);
    setError(null);
    try {
      await onConfirm();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Something went wrong.");
    } finally {
      setPending(false);
    }
  }

  return (
    <span className="inline-flex flex-col gap-1">
      <button
        type="button"
        onClick={handleClick}
        disabled={pending}
        className={`rounded-md px-2.5 py-1 text-xs font-medium disabled:opacity-60 ${
          variant === "danger"
            ? "bg-red-50 text-red-700 hover:bg-red-100 dark:bg-red-950 dark:text-red-400"
            : "bg-stone-100 text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300"
        }`}
      >
        {pending ? "…" : label}
      </button>
      {error ? <span className="text-xs text-red-600 dark:text-red-400">{error}</span> : null}
    </span>
  );
}
