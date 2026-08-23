"use client";

import { useState } from "react";
import { RequireCustomerAuth } from "@/components/account/RequireCustomerAuth";
import { AccountNav } from "@/components/account/AccountNav";
import { AddressForm } from "@/components/account/AddressForm";
import { ConfirmButton } from "@/components/admin/ui/ConfirmButton";
import { useAdminResource } from "@/lib/hooks/useAdminList";
import { getMyAddresses, createAddress, updateAddress, deleteAddress } from "@/lib/api/addresses";
import { Container } from "@/components/ui/Container";
import { EmptyState } from "@/components/ui/EmptyState";
import type { Address } from "@/types/api";

export default function AddressesPage() {
  return (
    <RequireCustomerAuth>
      <AddressBook />
    </RequireCustomerAuth>
  );
}

function AddressBook() {
  const { data: addresses, loading, refetch } = useAdminResource(getMyAddresses, [] as Address[]);
  const [adding, setAdding] = useState(false);
  const [editing, setEditing] = useState<Address | null>(null);

  return (
    <main className="flex-1 py-6 sm:py-10">
      <Container className="flex max-w-2xl flex-col gap-6">
        <AccountNav />
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-semibold tracking-tight text-stone-900 dark:text-stone-100">Your Addresses</h1>
          {!adding ? (
            <button
              type="button"
              onClick={() => setAdding(true)}
              className="rounded-md bg-stone-900 px-3 py-1.5 text-sm font-medium text-white dark:bg-amber-700"
            >
              Add address
            </button>
          ) : null}
        </div>

        {adding ? (
          <div className="rounded-lg border border-stone-200 p-4 dark:border-stone-800">
            <AddressForm
              submitLabel="Add address"
              onCancel={() => setAdding(false)}
              onSubmit={async (payload) => {
                await createAddress(payload);
                setAdding(false);
                await refetch();
              }}
            />
          </div>
        ) : null}

        {loading ? (
          <p className="text-sm text-stone-500">Loading…</p>
        ) : addresses.length === 0 && !adding ? (
          <EmptyState message="No saved addresses yet. Add one to speed up checkout." />
        ) : (
          <div className="flex flex-col gap-3">
            {addresses.map((address) =>
              editing?.id === address.id ? (
                <div key={address.id} className="rounded-lg border border-stone-200 p-4 dark:border-stone-800">
                  <AddressForm
                    initial={address}
                    submitLabel="Save changes"
                    onCancel={() => setEditing(null)}
                    onSubmit={async (payload) => {
                      await updateAddress(address.id, payload);
                      setEditing(null);
                      await refetch();
                    }}
                  />
                </div>
              ) : (
                <div key={address.id} className="flex items-start justify-between gap-3 rounded-lg border border-stone-200 p-4 dark:border-stone-800">
                  <div className="text-sm">
                    <p className="font-medium text-stone-900 dark:text-stone-100">
                      {address.label ? `${address.label} — ` : ""}
                      {address.recipient_name}
                      {address.is_default ? (
                        <span className="ml-2 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-400">
                          Default
                        </span>
                      ) : null}
                    </p>
                    <p className="text-stone-600 dark:text-stone-400">{address.phone}</p>
                    <p className="text-stone-600 dark:text-stone-400">
                      {address.line1}
                      {address.line2 ? `, ${address.line2}` : ""}
                    </p>
                    <p className="text-stone-600 dark:text-stone-400">
                      {[address.area, address.city].filter(Boolean).join(", ")}
                    </p>
                  </div>
                  <div className="flex shrink-0 gap-2">
                    <button
                      type="button"
                      onClick={() => setEditing(address)}
                      className="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300"
                    >
                      Edit
                    </button>
                    <ConfirmButton
                      label="Delete"
                      variant="danger"
                      confirmMessage="Delete this address?"
                      onConfirm={async () => {
                        await deleteAddress(address.id);
                        await refetch();
                      }}
                    />
                  </div>
                </div>
              ),
            )}
          </div>
        )}
      </Container>
    </main>
  );
}
