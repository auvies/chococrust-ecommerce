"use client";

import { useState } from "react";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { DataTable, type Column } from "@/components/admin/ui/DataTable";
import { Modal } from "@/components/admin/ui/Modal";
import { FormError } from "@/components/admin/ui/FormError";
import { CheckboxField, SelectField, TextField } from "@/components/admin/ui/fields";
import { useAdminResource } from "@/lib/hooks/useAdminList";
import { getAllSettings, upsertSetting } from "@/lib/api/admin/settings";
import { getSocialAccountStatus } from "@/lib/api/admin/social";
import { ApiError } from "@/lib/api/client";
import { PERMISSIONS } from "@/lib/permissions";
import type { SocialAccountStatus, SystemSetting } from "@/types/api";

export default function SettingsPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.settingsManage]}>
      <SettingsManager />
    </RequirePermission>
  );
}

function SettingsManager() {
  const [creating, setCreating] = useState(false);
  const [editing, setEditing] = useState<SystemSetting | null>(null);

  const { data: settings, loading, refetch: load } = useAdminResource(getAllSettings, [] as SystemSetting[]);
  const { data: socialAccounts, loading: socialLoading } = useAdminResource(getSocialAccountStatus, [] as SocialAccountStatus[]);

  const columns: Column<SystemSetting>[] = [
    { key: "key", header: "Key", render: (s) => s.key },
    { key: "value", header: "Value", render: (s) => s.value ?? "—" },
    { key: "group", header: "Group", render: (s) => s.group ?? "—" },
    { key: "public", header: "Public", render: (s) => (s.is_public ? "Yes" : "No") },
    {
      key: "actions",
      header: "",
      render: (s) => (
        <button type="button" onClick={() => setEditing(s)} className="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300">
          Edit
        </button>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Settings"
        action={
          <button type="button" onClick={() => setCreating(true)} className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white dark:bg-amber-700">
            Add Setting
          </button>
        }
      />
      <DataTable columns={columns} rows={settings} rowKey={(s) => s.key} loading={loading} emptyMessage="No settings configured yet." />

      <section className="mt-8 max-w-md rounded-lg border border-stone-200 p-4 dark:border-stone-800">
        <h2 className="mb-1 text-sm font-semibold text-stone-900 dark:text-stone-100">Social Integrations</h2>
        <p className="mb-3 text-xs text-stone-500">
          Prepared, not yet built — no Facebook/Instagram account is connected. Credentials, when added, live in
          server environment variables only and are never exposed here or anywhere in the frontend.
        </p>
        {socialLoading ? (
          <p className="text-sm text-stone-500">Loading…</p>
        ) : (
          <ul className="flex flex-col gap-2">
            {socialAccounts.map((account) => (
              <li key={account.platform} className="flex items-center justify-between text-sm">
                <span className="capitalize">{account.platform}</span>
                <span className={account.connected ? "text-green-700 dark:text-green-400" : "text-stone-500"}>
                  {account.connected ? "Connected" : "Not connected"}
                </span>
              </li>
            ))}
          </ul>
        )}
      </section>

      {creating ? (
        <SettingModal
          initialKey=""
          initial={{ value: "", type: "string", group: "", is_public: false }}
          onClose={() => setCreating(false)}
          onSaved={async () => { setCreating(false); await load(); }}
        />
      ) : null}
      {editing ? (
        <SettingModal
          initialKey={editing.key}
          initial={{ value: editing.value ?? "", type: editing.type as "string" | "number" | "boolean" | "json", group: editing.group ?? "", is_public: editing.is_public ?? false }}
          onClose={() => setEditing(null)}
          onSaved={async () => { setEditing(null); await load(); }}
        />
      ) : null}
    </div>
  );
}

function SettingModal({
  initialKey,
  initial,
  onClose,
  onSaved,
}: {
  initialKey: string;
  initial: { value: string; type: "string" | "number" | "boolean" | "json"; group: string; is_public: boolean };
  onClose: () => void;
  onSaved: () => Promise<void>;
}) {
  const [key, setKey] = useState(initialKey);
  const [value, setValue] = useState(initial.value);
  const [type, setType] = useState(initial.type);
  const [group, setGroup] = useState(initial.group);
  const [isPublic, setIsPublic] = useState(initial.is_public);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]> | undefined>();
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit() {
    setSubmitting(true);
    setError(null);
    try {
      await upsertSetting({ key, value, type, group: group || null, is_public: isPublic });
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
    <Modal title={initialKey ? `Edit ${initialKey}` : "Add Setting"} onClose={onClose}>
      <FormError message={error} fieldErrors={fieldErrors} />
      <TextField id="key" label="Key" required value={key} onChange={setKey} placeholder="e.g. contact.email" />
      <TextField id="value" label="Value" required value={value} onChange={setValue} />
      <SelectField id="type" label="Type" value={type} onChange={setType} options={[{ value: "string", label: "String" }, { value: "number", label: "Number" }, { value: "boolean", label: "Boolean" }, { value: "json", label: "JSON" }]} />
      <TextField id="group" label="Group" value={group} onChange={setGroup} />
      <CheckboxField id="is_public" label="Public (visible to the storefront)" checked={isPublic} onChange={setIsPublic} />
      <div className="mt-4 flex justify-end gap-2">
        <button type="button" onClick={onClose} className="rounded-md border border-stone-300 px-4 py-2 text-sm dark:border-stone-700">Cancel</button>
        <button type="button" disabled={submitting} onClick={handleSubmit} className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700">
          {submitting ? "Saving…" : "Save"}
        </button>
      </div>
    </Modal>
  );
}
