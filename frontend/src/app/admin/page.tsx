"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useAdminAuth } from "@/components/admin/AuthProvider";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { getAnalyticsOverview, type AnalyticsOverview } from "@/lib/api/admin/analytics";
import { formatPrice } from "@/lib/format";
import { PERMISSIONS, visibleModules } from "@/lib/permissions";

export default function AdminDashboardPage() {
  const { user, hasPermission } = useAdminAuth();
  const [overview, setOverview] = useState<AnalyticsOverview | null>(null);

  useEffect(() => {
    if (hasPermission(PERMISSIONS.analyticsView)) {
      getAnalyticsOverview().then(setOverview).catch(() => setOverview(null));
    }
  }, [hasPermission]);

  const modules = visibleModules(user).filter((m) => m.key !== "dashboard");

  return (
    <div>
      <PageHeader title={`Welcome, ${user?.name ?? "there"}`} />

      {overview ? (
        <div className="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-4">
          <StatCard label="Total orders" value={String(overview.orders_total)} />
          <StatCard label="Revenue collected" value={formatPrice(overview.revenue_paid)} />
          {Object.entries(overview.orders_by_status).map(([status, count]) => (
            <StatCard key={status} label={`Orders: ${status}`} value={String(count)} />
          ))}
        </div>
      ) : null}

      <h2 className="mb-3 text-sm font-semibold text-stone-900 dark:text-stone-100">Sections you can access</h2>
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        {modules.map((m) => (
          <Link
            key={m.key}
            href={m.href}
            className="rounded-lg border border-stone-200 bg-white p-4 text-sm font-medium text-stone-800 hover:shadow-md dark:border-stone-800 dark:bg-stone-900 dark:text-stone-200"
          >
            {m.label}
          </Link>
        ))}
      </div>
    </div>
  );
}

function StatCard({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-lg border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
      <p className="text-xs uppercase tracking-wide text-stone-500">{label}</p>
      <p className="mt-1 text-lg font-semibold text-stone-900 dark:text-stone-100">{value}</p>
    </div>
  );
}
