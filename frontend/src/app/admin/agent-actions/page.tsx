"use client";

import { useState } from "react";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { DataTable, type Column } from "@/components/admin/ui/DataTable";
import { FilterTabs } from "@/components/admin/ui/FilterTabs";
import { StatusBadge } from "@/components/admin/ui/StatusBadge";
import { ConfirmButton } from "@/components/admin/ui/ConfirmButton";
import { Modal } from "@/components/admin/ui/Modal";
import { FormError } from "@/components/admin/ui/FormError";
import { TextAreaField } from "@/components/admin/ui/fields";
import { useAdminList } from "@/lib/hooks/useAdminList";
import { getPendingAgentActions, approveAgentAction, rejectAgentAction } from "@/lib/api/admin/agentActions";
import { ApiError } from "@/lib/api/client";
import { formatDate } from "@/lib/format";
import { PERMISSIONS } from "@/lib/permissions";
import type { AgentPendingAction } from "@/types/api";

export default function AgentActionsPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.agentActionsManage]}>
      <AgentActionsManager />
    </RequirePermission>
  );
}

type View = "pending" | "executed" | "rejected" | "failed" | "all";

const VIEWS: { key: View; label: string }[] = [
  { key: "pending", label: "Pending" },
  { key: "executed", label: "Executed" },
  { key: "rejected", label: "Rejected" },
  { key: "failed", label: "Failed" },
  { key: "all", label: "All" },
];

function AgentActionsManager() {
  const [view, setView] = useState<View>("pending");
  const [rejecting, setRejecting] = useState<AgentPendingAction | null>(null);
  const [error, setError] = useState<string | null>(null);

  const { data, loading, refetch } = useAdminList<AgentPendingAction>(
    () => getPendingAgentActions(view === "all" ? { sort: "-created_at", per_page: 50 } : { filter: { status: view }, sort: "-created_at", per_page: 50 }),
    view,
  );

  async function handleApprove(action: AgentPendingAction) {
    setError(null);
    try {
      await approveAgentAction(action.id);
      await refetch();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Something went wrong.");
    }
  }

  const columns: Column<AgentPendingAction>[] = [
    { key: "tool", header: "Tool", render: (a) => a.tool_name },
    { key: "agent_type", header: "Agent type", render: (a) => a.agent_type ?? "—" },
    { key: "input", header: "Requested input", render: (a) => <code className="text-xs">{JSON.stringify(a.input)}</code> },
    { key: "status", header: "Status", render: (a) => <StatusBadge status={a.status} /> },
    { key: "requested", header: "Requested", render: (a) => formatDate(a.created_at) },
    {
      key: "actions",
      header: "",
      render: (a) =>
        a.status === "pending" ? (
          <div className="flex gap-2">
            <ConfirmButton
              label="Approve"
              confirmMessage={`Approve "${a.tool_name}"? This executes it immediately.`}
              onConfirm={() => handleApprove(a)}
            />
            <button
              type="button"
              onClick={() => setRejecting(a)}
              className="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300"
            >
              Reject
            </button>
          </div>
        ) : a.status === "failed" ? (
          <span className="text-xs text-red-600 dark:text-red-400">{a.error}</span>
        ) : a.status === "rejected" ? (
          <span className="text-xs text-stone-500">{a.rejection_reason}</span>
        ) : null,
    },
  ];

  return (
    <div>
      <PageHeader title="Agent Actions" />
      <p className="-mt-2 mb-4 text-sm text-stone-500 dark:text-stone-400">
        High-risk requests from AI agent identities (refunds, order cancellations, ...) never execute on their own -
        approving one runs it immediately as you; approving still requires the same permission the action itself
        would (e.g. issuing a refund directly needs orders.refund).
      </p>
      <FormError message={error} />
      <FilterTabs tabs={VIEWS} active={view} onChange={setView} />
      <DataTable columns={columns} rows={data} rowKey={(a) => a.id} loading={loading} emptyMessage="Nothing here." />

      {rejecting ? (
        <RejectModal
          action={rejecting}
          onClose={() => setRejecting(null)}
          onRejected={async () => {
            setRejecting(null);
            await refetch();
          }}
        />
      ) : null}
    </div>
  );
}

function RejectModal({ action, onClose, onRejected }: { action: AgentPendingAction; onClose: () => void; onRejected: () => Promise<void> }) {
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]> | undefined>();
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit() {
    setSubmitting(true);
    setError(null);
    try {
      await rejectAgentAction(action.id, reason);
      await onRejected();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
        setFieldErrors(err.errors);
      } else {
        setError("Something went wrong.");
      }
      setSubmitting(false);
    }
  }

  return (
    <Modal title={`Reject — ${action.tool_name}`} onClose={onClose}>
      <FormError message={error} fieldErrors={fieldErrors} />
      <TextAreaField id="reason" label="Reason (shown in the audit trail)" value={reason} onChange={setReason} rows={3} />
      <div className="mt-4 flex justify-end gap-2">
        <button type="button" onClick={onClose} className="rounded-md border border-stone-300 px-4 py-2 text-sm dark:border-stone-700">Cancel</button>
        <button
          type="button"
          disabled={submitting || !reason.trim()}
          onClick={handleSubmit}
          className="rounded-md bg-red-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
        >
          {submitting ? "Rejecting…" : "Reject"}
        </button>
      </div>
    </Modal>
  );
}
