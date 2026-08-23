"use client";

import { RequireCustomerAuth } from "@/components/account/RequireCustomerAuth";
import { AccountNav } from "@/components/account/AccountNav";
import { useAdminList } from "@/lib/hooks/useAdminList";
import { getMyNotifications, markNotificationRead, markAllNotificationsRead } from "@/lib/api/notifications";
import { formatDate } from "@/lib/format";
import { Container } from "@/components/ui/Container";
import { EmptyState } from "@/components/ui/EmptyState";
import type { AppNotification } from "@/types/api";

export default function AccountNotificationsPage() {
  return (
    <RequireCustomerAuth>
      <NotificationsList />
    </RequireCustomerAuth>
  );
}

function NotificationsList() {
  const { data: notifications, loading, refetch } = useAdminList<AppNotification>(() =>
    getMyNotifications({ sort: "-created_at", per_page: 30 }),
  );

  const unreadCount = notifications.filter((n) => !n.read_at).length;

  return (
    <main className="flex-1 py-6 sm:py-10">
      <Container className="flex max-w-2xl flex-col gap-6">
        <AccountNav />

        <div className="flex items-center justify-between gap-3">
          <h1 className="text-2xl font-semibold tracking-tight text-stone-900 dark:text-stone-100">Notifications</h1>
          {unreadCount > 0 ? (
            <button
              type="button"
              onClick={async () => {
                await markAllNotificationsRead();
                await refetch();
              }}
              className="text-sm font-medium text-amber-800 hover:underline dark:text-amber-500"
            >
              Mark all as read
            </button>
          ) : null}
        </div>

        {loading ? (
          <p className="text-sm text-stone-500">Loading…</p>
        ) : notifications.length === 0 ? (
          <EmptyState message="No notifications yet — updates about your orders, payments, and deliveries will show up here." />
        ) : (
          <ul className="flex flex-col gap-2">
            {notifications.map((notification) => (
              <li
                key={notification.id}
                className={`rounded-lg border p-4 text-sm ${
                  notification.read_at
                    ? "border-stone-200 dark:border-stone-800"
                    : "border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950/30"
                }`}
              >
                <div className="flex items-start justify-between gap-3">
                  <p className="text-stone-800 dark:text-stone-200">{notification.data.body ?? "Update on your account."}</p>
                  {!notification.read_at ? (
                    <button
                      type="button"
                      onClick={async () => {
                        await markNotificationRead(notification.id);
                        await refetch();
                      }}
                      className="shrink-0 text-xs font-medium text-amber-800 hover:underline dark:text-amber-500"
                    >
                      Mark read
                    </button>
                  ) : null}
                </div>
                <p className="mt-1 text-xs text-stone-500 dark:text-stone-500">{formatDate(notification.created_at)}</p>
              </li>
            ))}
          </ul>
        )}
      </Container>
    </main>
  );
}
