"use client";

import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { useAdminResource } from "@/lib/hooks/useAdminList";
import {
  getBestSellers,
  getCodAnalytics,
  getCustomerAnalytics,
  getDeliveryAnalytics,
  getInventoryAnalytics,
  getOrderAnalytics,
  getPaymentAnalytics,
  getProductPerformance,
  type CodAnalytics,
  type CustomerAnalytics,
  type DeliveryAnalytics,
  type InventoryAnalytics,
  type OrderAnalytics,
  type PaymentAnalytics,
  type ProductPerformanceRow,
} from "@/lib/api/admin/analytics";
import { formatPrice } from "@/lib/format";
import { PERMISSIONS } from "@/lib/permissions";

export default function AnalyticsPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.analyticsView]}>
      <AnalyticsDashboard />
    </RequirePermission>
  );
}

const EMPTY_ORDERS: OrderAnalytics = { orders_total: 0, by_status: {}, average_order_value: 0, cancelled_orders: 0, cancellation_rate: 0 };
const EMPTY_CUSTOMERS: CustomerAnalytics = { new_customers: 0, customers_with_orders: 0, repeat_customers: 0, repeat_customer_rate: 0, top_customers: [] };
const EMPTY_COD: CodAnalytics = { total_cod_records: 0, by_status: {}, collected: 0, failed: 0, awaiting_delivery: 0, success_rate: null };
const EMPTY_DELIVERY: DeliveryAnalytics = { total_deliveries: 0, by_status: {}, delivered: 0, failed: 0, returned: 0, failed_delivery_rate: null, average_delivery_attempts: 0 };
const EMPTY_INVENTORY: InventoryAnalytics = { inventory_records_tracked: 0, total_units_on_hand: 0, total_units_available: 0, out_of_stock_count: 0, low_stock_count: 0, low_stock: [] };
const EMPTY_PAYMENTS: PaymentAnalytics = { total_payments: 0, total_paid_amount: 0, by_method: [], by_status: {}, failure_rate: 0, refund_rate: 0 };

function percent(value: number | null): string {
  return value === null ? "—" : `${(value * 100).toFixed(1)}%`;
}

function AnalyticsDashboard() {
  const orders = useAdminResource(getOrderAnalytics, EMPTY_ORDERS);
  const bestSellers = useAdminResource(getBestSellers, [] as ProductPerformanceRow[]);
  const productPerformance = useAdminResource(getProductPerformance, [] as ProductPerformanceRow[]);
  const customers = useAdminResource(getCustomerAnalytics, EMPTY_CUSTOMERS);
  const cod = useAdminResource(getCodAnalytics, EMPTY_COD);
  const delivery = useAdminResource(getDeliveryAnalytics, EMPTY_DELIVERY);
  const inventory = useAdminResource(getInventoryAnalytics, EMPTY_INVENTORY);
  const payments = useAdminResource(getPaymentAnalytics, EMPTY_PAYMENTS);

  const loading = [orders, bestSellers, productPerformance, customers, cod, delivery, inventory, payments].some((r) => r.loading);

  return (
    <div>
      <PageHeader title="Analytics" />
      <p className="-mt-2 mb-6 text-sm text-stone-500 dark:text-stone-400">
        Last 30 days unless noted. Inventory is a live snapshot, not date-ranged.
      </p>

      {loading ? (
        <p className="text-sm text-stone-500">Loading…</p>
      ) : (
        <div className="space-y-8">
          <section>
            <h2 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">Orders</h2>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
              <StatCard label="Total orders" value={String(orders.data.orders_total)} />
              <StatCard label="Average order value" value={formatPrice(orders.data.average_order_value)} />
              <StatCard label="Cancelled" value={String(orders.data.cancelled_orders)} />
              <StatCard label="Cancellation rate" value={percent(orders.data.cancellation_rate)} />
            </div>
          </section>

          <section>
            <h2 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">Best sellers (by quantity)</h2>
            <ProductTable rows={bestSellers.data} metric="quantity_sold" metricLabel="Units sold" />
          </section>

          <section>
            <h2 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">Product performance (by revenue)</h2>
            <ProductTable rows={productPerformance.data} metric="revenue" metricLabel="Revenue" isCurrency />
          </section>

          <section>
            <h2 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">Customers</h2>
            <div className="mb-3 grid grid-cols-2 gap-4 sm:grid-cols-4">
              <StatCard label="New customers" value={String(customers.data.new_customers)} />
              <StatCard label="Customers with orders" value={String(customers.data.customers_with_orders)} />
              <StatCard label="Repeat customers" value={String(customers.data.repeat_customers)} />
              <StatCard label="Repeat rate" value={percent(customers.data.repeat_customer_rate)} />
            </div>
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-stone-200 text-xs uppercase tracking-wide text-stone-500 dark:border-stone-800">
                  <th className="py-2">Customer</th>
                  <th className="py-2">Orders</th>
                  <th className="py-2">Total spent</th>
                </tr>
              </thead>
              <tbody>
                {customers.data.top_customers.map((c) => (
                  <tr key={c.customer_id} className="border-b border-stone-100 dark:border-stone-900">
                    <td className="py-2">{c.name ?? c.email ?? `Customer #${c.customer_id}`}</td>
                    <td className="py-2">{c.orders_count}</td>
                    <td className="py-2">{formatPrice(c.total_spent)}</td>
                  </tr>
                ))}
                {customers.data.top_customers.length === 0 ? (
                  <tr><td className="py-2 text-stone-500" colSpan={3}>No orders yet.</td></tr>
                ) : null}
              </tbody>
            </table>
          </section>

          <section>
            <h2 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">COD collection success rate</h2>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
              <StatCard label="Success rate" value={percent(cod.data.success_rate)} />
              <StatCard label="Collected" value={String(cod.data.collected)} />
              <StatCard label="Failed" value={String(cod.data.failed)} />
              <StatCard label="Awaiting delivery" value={String(cod.data.awaiting_delivery)} />
            </div>
          </section>

          <section>
            <h2 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">Delivery performance</h2>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
              <StatCard label="Failed delivery rate" value={percent(delivery.data.failed_delivery_rate)} />
              <StatCard label="Delivered" value={String(delivery.data.delivered)} />
              <StatCard label="Failed + returned" value={String(delivery.data.failed + delivery.data.returned)} />
              <StatCard label="Avg. delivery attempts" value={delivery.data.average_delivery_attempts.toFixed(2)} />
            </div>
          </section>

          <section>
            <h2 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">Inventory</h2>
            <div className="mb-3 grid grid-cols-2 gap-4 sm:grid-cols-4">
              <StatCard label="Units on hand" value={String(inventory.data.total_units_on_hand)} />
              <StatCard label="Units available" value={String(inventory.data.total_units_available)} />
              <StatCard label="Out of stock" value={String(inventory.data.out_of_stock_count)} />
              <StatCard label="Low stock" value={String(inventory.data.low_stock_count)} />
            </div>
            {inventory.data.low_stock.length > 0 ? (
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-stone-200 text-xs uppercase tracking-wide text-stone-500 dark:border-stone-800">
                    <th className="py-2">SKU</th>
                    <th className="py-2">Variant</th>
                    <th className="py-2">Available</th>
                    <th className="py-2">Reorder level</th>
                  </tr>
                </thead>
                <tbody>
                  {inventory.data.low_stock.map((row) => (
                    <tr key={row.product_variant_id} className="border-b border-stone-100 dark:border-stone-900">
                      <td className="py-2">{row.sku}</td>
                      <td className="py-2">{row.name}</td>
                      <td className="py-2">{row.available}</td>
                      <td className="py-2">{row.reorder_level}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : null}
          </section>

          <section>
            <h2 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">Payments</h2>
            <div className="mb-3 grid grid-cols-2 gap-4 sm:grid-cols-4">
              <StatCard label="Total paid" value={formatPrice(payments.data.total_paid_amount)} />
              <StatCard label="Failure rate" value={percent(payments.data.failure_rate)} />
              <StatCard label="Refund rate" value={percent(payments.data.refund_rate)} />
              <StatCard label="Total payments" value={String(payments.data.total_payments)} />
            </div>
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-stone-200 text-xs uppercase tracking-wide text-stone-500 dark:border-stone-800">
                  <th className="py-2">Method</th>
                  <th className="py-2">Count</th>
                  <th className="py-2">Total amount</th>
                </tr>
              </thead>
              <tbody>
                {payments.data.by_method.map((row) => (
                  <tr key={row.method} className="border-b border-stone-100 dark:border-stone-900">
                    <td className="py-2 capitalize">{row.method}</td>
                    <td className="py-2">{row.count}</td>
                    <td className="py-2">{formatPrice(row.total_amount)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </section>
        </div>
      )}
    </div>
  );
}

function ProductTable({
  rows,
  metric,
  metricLabel,
  isCurrency,
}: {
  rows: ProductPerformanceRow[];
  metric: "quantity_sold" | "revenue";
  metricLabel: string;
  isCurrency?: boolean;
}) {
  return (
    <table className="w-full text-left text-sm">
      <thead>
        <tr className="border-b border-stone-200 text-xs uppercase tracking-wide text-stone-500 dark:border-stone-800">
          <th className="py-2">Product</th>
          <th className="py-2">{metricLabel}</th>
          <th className="py-2">Orders</th>
        </tr>
      </thead>
      <tbody>
        {rows.map((row) => (
          <tr key={row.product_id} className="border-b border-stone-100 dark:border-stone-900">
            <td className="py-2">{row.name}</td>
            <td className="py-2">{isCurrency ? formatPrice(row[metric]) : row[metric]}</td>
            <td className="py-2">{row.order_count}</td>
          </tr>
        ))}
        {rows.length === 0 ? (
          <tr><td className="py-2 text-stone-500" colSpan={3}>No sales data yet.</td></tr>
        ) : null}
      </tbody>
    </table>
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
