"use client";

import { useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { FormError } from "@/components/admin/ui/FormError";
import { StatusBadge } from "@/components/admin/ui/StatusBadge";
import { TextAreaField, TextField } from "@/components/admin/ui/fields";
import { useAdminAuth } from "@/components/admin/AuthProvider";
import { useAdminResource } from "@/lib/hooks/useAdminList";
import {
  getCustomer,
  updateCustomerNotes,
  getCustomerOrders,
  getCustomerNotes,
  addCustomerNote,
  getCustomerTags,
  attachCustomerTag,
  detachCustomerTag,
  createCustomerTag,
} from "@/lib/api/admin/customers";
import { ApiError } from "@/lib/api/client";
import { formatDate, formatPrice } from "@/lib/format";
import { PERMISSIONS } from "@/lib/permissions";
import type { Customer, CustomerNote, CustomerTag, Order } from "@/types/api";

export default function CustomerDetailPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.customersView, PERMISSIONS.customersManage]}>
      <CustomerDetail />
    </RequirePermission>
  );
}

function CustomerDetail() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const { hasPermission } = useAdminAuth();
  const canManage = hasPermission(PERMISSIONS.customersManage);

  const { data: customer, loading, refetch } = useAdminResource<Customer | null>(() => getCustomer(id), null, id);
  const { data: orders, loading: ordersLoading } = useAdminResource<Order[]>(
    async () => (await getCustomerOrders(id)).data,
    [],
    id,
  );

  if (loading || !customer) return <p className="text-sm text-stone-500">Loading…</p>;

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={customer.name ?? "Customer"} />

      <section className="max-w-md rounded-lg border border-stone-200 p-4 text-sm dark:border-stone-800">
        <p><span className="text-stone-500">Email:</span> {customer.email ?? "—"}</p>
        <p><span className="text-stone-500">Phone:</span> {customer.phone ?? "—"}</p>
        <p><span className="text-stone-500">Marketing opt-in:</span> {customer.marketing_opt_in ? "Yes" : "No"}</p>
      </section>

      <TagsSection customer={customer} canManage={canManage} onChanged={refetch} />

      <section>
        <h2 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">Addresses</h2>
        {customer.addresses.length === 0 ? (
          <p className="text-sm text-stone-500">No addresses on file.</p>
        ) : (
          <ul className="flex flex-col gap-2 text-sm text-stone-700 dark:text-stone-300">
            {customer.addresses.map((a) => (
              <li key={a.id} className="rounded-md border border-stone-200 p-3 dark:border-stone-800">
                <p className="font-medium">{a.recipient_name} {a.is_default ? "(default)" : ""}</p>
                <p>{a.line1}{a.line2 ? `, ${a.line2}` : ""}, {a.city}</p>
                <p>{a.phone}</p>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section>
        <h2 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">Order history</h2>
        {ordersLoading ? (
          <p className="text-sm text-stone-500">Loading…</p>
        ) : orders.length === 0 ? (
          <p className="text-sm text-stone-500">No orders yet.</p>
        ) : (
          <ul className="flex flex-col gap-2 text-sm">
            {orders.map((order) => (
              <li key={order.id}>
                <Link
                  href={`/admin/orders/${order.id}`}
                  className="flex items-center justify-between gap-3 rounded-md border border-stone-200 p-3 hover:border-amber-700 dark:border-stone-800 dark:hover:border-amber-600"
                >
                  <div>
                    <p className="font-medium text-stone-900 dark:text-stone-100">{order.order_number}</p>
                    <p className="text-xs text-stone-500">{formatDate(order.placed_at ?? order.created_at)}</p>
                  </div>
                  <div className="flex items-center gap-3">
                    <StatusBadge status={order.status} />
                    <span className="font-medium text-stone-900 dark:text-stone-100">{formatPrice(order.total, order.currency)}</span>
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </section>

      {canManage ? <NotesEditor customer={customer} /> : null}
      {canManage ? <StaffNotesLog customerId={customer.id} /> : null}
    </div>
  );
}

function NotesEditor({ customer }: { customer: Customer }) {
  const [notes, setNotes] = useState(customer.notes ?? "");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSaveNotes() {
    setSaving(true);
    setError(null);
    try {
      await updateCustomerNotes(customer.id, notes);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Something went wrong.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <section className="max-w-xl rounded-lg border border-stone-200 p-4 dark:border-stone-800">
      <h2 className="mb-3 text-sm font-semibold text-stone-900 dark:text-stone-100">Summary note</h2>
      <FormError message={error} />
      <TextAreaField id="notes" label="Quick-glance note (staff-only, never shown to the customer)" value={notes} onChange={setNotes} rows={3} />
      <button type="button" onClick={handleSaveNotes} disabled={saving} className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700">
        {saving ? "Saving…" : "Save"}
      </button>
    </section>
  );
}

/** The structured, append-only, multi-author note log - distinct from the single overwritable summary note above. */
function StaffNotesLog({ customerId }: { customerId: number }) {
  const [draft, setDraft] = useState("");
  const [posting, setPosting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const { data: notes, loading, refetch } = useAdminResource<CustomerNote[]>(() => getCustomerNotes(customerId), [], customerId);

  async function handleAdd() {
    if (!draft.trim()) return;
    setPosting(true);
    setError(null);
    try {
      await addCustomerNote(customerId, draft.trim());
      setDraft("");
      await refetch();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Something went wrong.");
    } finally {
      setPosting(false);
    }
  }

  return (
    <section className="max-w-xl rounded-lg border border-stone-200 p-4 dark:border-stone-800">
      <h2 className="mb-3 text-sm font-semibold text-stone-900 dark:text-stone-100">Staff notes log</h2>
      <FormError message={error} />
      <TextAreaField id="new-note" label="Add a note (timestamped, attributed to you, cannot be edited or deleted)" value={draft} onChange={setDraft} rows={2} />
      <button type="button" onClick={handleAdd} disabled={posting || !draft.trim()} className="mb-4 rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700">
        {posting ? "Adding…" : "Add note"}
      </button>

      {loading ? (
        <p className="text-sm text-stone-500">Loading…</p>
      ) : notes.length === 0 ? (
        <p className="text-sm text-stone-500">No notes yet.</p>
      ) : (
        <ul className="flex flex-col gap-2">
          {notes.map((note) => (
            <li key={note.id} className="rounded-md bg-stone-50 p-3 text-sm dark:bg-stone-900">
              <p className="text-stone-800 dark:text-stone-200">{note.body}</p>
              <p className="mt-1 text-xs text-stone-500">{note.author_name ?? "Unknown"} · {formatDate(note.created_at)}</p>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

function TagsSection({ customer, canManage, onChanged }: { customer: Customer; canManage: boolean; onChanged: () => Promise<void> }) {
  const [adding, setAdding] = useState(false);
  const [newTagName, setNewTagName] = useState("");
  const [error, setError] = useState<string | null>(null);
  const { data: allTags, refetch: refetchTags } = useAdminResource<CustomerTag[]>(getCustomerTags, []);

  const assignedIds = new Set((customer.tags ?? []).map((t) => t.id));
  const available = allTags.filter((t) => !assignedIds.has(t.id));

  async function handleAttach(tagId: number) {
    setError(null);
    try {
      await attachCustomerTag(customer.id, tagId);
      await onChanged();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Something went wrong.");
    }
  }

  async function handleDetach(tagId: number) {
    setError(null);
    try {
      await detachCustomerTag(customer.id, tagId);
      await onChanged();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Something went wrong.");
    }
  }

  async function handleCreateAndAttach() {
    if (!newTagName.trim()) return;
    setError(null);
    try {
      const tag = await createCustomerTag(newTagName.trim());
      await attachCustomerTag(customer.id, tag.id);
      setNewTagName("");
      setAdding(false);
      await Promise.all([refetchTags(), onChanged()]);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Something went wrong.");
    }
  }

  return (
    <section className="max-w-xl">
      <h2 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">Tags</h2>
      <FormError message={error} />
      <div className="flex flex-wrap items-center gap-2">
        {(customer.tags ?? []).map((tag) => (
          <span
            key={tag.id}
            className="flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-900 dark:bg-amber-900/40 dark:text-amber-300"
          >
            {tag.name}
            {canManage ? (
              <button type="button" onClick={() => handleDetach(tag.id)} aria-label={`Remove ${tag.name}`} className="ml-1 text-amber-700 hover:text-amber-900 dark:text-amber-400">
                ×
              </button>
            ) : null}
          </span>
        ))}
        {(customer.tags ?? []).length === 0 ? <span className="text-sm text-stone-500">No tags.</span> : null}
      </div>

      {canManage ? (
        <div className="mt-2 flex flex-wrap items-center gap-2">
          {available.map((tag) => (
            <button
              key={tag.id}
              type="button"
              onClick={() => handleAttach(tag.id)}
              className="rounded-full border border-dashed border-stone-300 px-3 py-1 text-xs text-stone-600 hover:border-amber-700 hover:text-amber-800 dark:border-stone-700 dark:text-stone-400"
            >
              + {tag.name}
            </button>
          ))}
          {adding ? (
            <span className="flex items-center gap-2">
              <TextField id="new-tag" label="" value={newTagName} onChange={setNewTagName} placeholder="New tag name" />
              <button type="button" onClick={handleCreateAndAttach} className="rounded-md bg-stone-900 px-3 py-2 text-xs font-medium text-white dark:bg-amber-700">
                Add
              </button>
            </span>
          ) : (
            <button type="button" onClick={() => setAdding(true)} className="text-xs font-medium text-amber-800 hover:underline dark:text-amber-500">
              + New tag
            </button>
          )}
        </div>
      ) : null}
    </section>
  );
}
