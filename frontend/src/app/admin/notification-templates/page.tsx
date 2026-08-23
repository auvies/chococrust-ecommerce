"use client";

import { useState } from "react";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { DataTable, type Column } from "@/components/admin/ui/DataTable";
import { Modal } from "@/components/admin/ui/Modal";
import { FormError } from "@/components/admin/ui/FormError";
import { CheckboxField, TextField, TextAreaField } from "@/components/admin/ui/fields";
import { useAdminResource } from "@/lib/hooks/useAdminList";
import { getNotificationTemplates, updateNotificationTemplate } from "@/lib/api/admin/notificationTemplates";
import { ApiError } from "@/lib/api/client";
import { PERMISSIONS } from "@/lib/permissions";
import type { NotificationTemplate } from "@/types/api";

export default function NotificationTemplatesPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.notificationsManage]}>
      <NotificationTemplatesManager />
    </RequirePermission>
  );
}

function NotificationTemplatesManager() {
  const [editing, setEditing] = useState<NotificationTemplate | null>(null);
  const { data: templates, loading, refetch: load } = useAdminResource(getNotificationTemplates, [] as NotificationTemplate[]);

  const columns: Column<NotificationTemplate>[] = [
    { key: "key", header: "Event", render: (t) => t.key },
    { key: "channel", header: "Channel", render: (t) => t.channel },
    { key: "subject", header: "Subject", render: (t) => t.subject ?? "—" },
    { key: "body", header: "Body", render: (t) => <span className="line-clamp-2 block max-w-sm">{t.body}</span> },
    { key: "status", header: "Active", render: (t) => (t.is_active ? "Yes" : "No") },
    {
      key: "actions",
      header: "",
      render: (t) => (
        <button
          type="button"
          onClick={() => setEditing(t)}
          className="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300"
        >
          Edit
        </button>
      ),
    },
  ];

  return (
    <div>
      <PageHeader title="Notification Templates" />
      <p className="-mt-2 mb-4 text-sm text-stone-500 dark:text-stone-400">
        Order/payment/delivery notification copy. <code>{"{placeholder}"}</code> tokens (e.g. <code>{"{order_number}"}</code>) are
        filled in when a notification is sent. Deactivating a template falls back to a safe built-in message rather than sending nothing.
      </p>
      <DataTable columns={columns} rows={templates} rowKey={(t) => t.id} loading={loading} emptyMessage="No templates found." />

      {editing ? (
        <TemplateModal
          template={editing}
          onClose={() => setEditing(null)}
          onSaved={async () => { setEditing(null); await load(); }}
        />
      ) : null}
    </div>
  );
}

function TemplateModal({
  template,
  onClose,
  onSaved,
}: {
  template: NotificationTemplate;
  onClose: () => void;
  onSaved: () => Promise<void>;
}) {
  const [subject, setSubject] = useState(template.subject ?? "");
  const [body, setBody] = useState(template.body);
  const [isActive, setIsActive] = useState(template.is_active);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]> | undefined>();
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit() {
    setSubmitting(true);
    setError(null);
    try {
      await updateNotificationTemplate(template.id, {
        subject: template.channel === "mail" ? subject : null,
        body,
        is_active: isActive,
      });
      await onSaved();
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
    <Modal title={`${template.key} · ${template.channel}`} onClose={onClose}>
      <FormError message={error} fieldErrors={fieldErrors} />
      {template.channel === "mail" ? (
        <TextField id="subject" label="Subject" value={subject} onChange={setSubject} />
      ) : null}
      <TextAreaField id="body" label="Body" value={body} onChange={setBody} rows={4} />
      <CheckboxField id="is_active" label="Active" checked={isActive} onChange={setIsActive} />
      <div className="mt-4 flex justify-end gap-2">
        <button type="button" onClick={onClose} className="rounded-md border border-stone-300 px-4 py-2 text-sm dark:border-stone-700">Cancel</button>
        <button type="button" disabled={submitting} onClick={handleSubmit} className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700">
          {submitting ? "Saving…" : "Save"}
        </button>
      </div>
    </Modal>
  );
}
