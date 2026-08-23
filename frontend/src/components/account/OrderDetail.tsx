import { StatusBadge } from "@/components/admin/ui/StatusBadge";
import { formatPrice, formatDate } from "@/lib/format";
import type { Order } from "@/types/api";

/** Order lifecycle (pending → confirmed → processing → ready → dispatched → delivered → completed), mirrors OrderService::TRANSITIONS. */
const STATUS_TIMELINE = ["pending", "confirmed", "processing", "ready", "dispatched", "delivered", "completed"] as const;

export function OrderDetail({ order }: { order: Order }) {
  const currentIndex = STATUS_TIMELINE.indexOf(order.status as (typeof STATUS_TIMELINE)[number]);
  const isTerminalOffTrack = order.status === "cancelled" || order.status === "refunded";

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 className="text-lg font-semibold text-stone-900 dark:text-stone-100">Order {order.order_number}</h2>
          <p className="text-sm text-stone-500 dark:text-stone-400">
            Placed {order.placed_at ? formatDate(order.placed_at) : formatDate(order.created_at)}
          </p>
        </div>
        <StatusBadge status={order.status} />
      </div>

      {/* Order status timeline */}
      <section>
        <h3 className="mb-3 text-sm font-semibold text-stone-900 dark:text-stone-100">Order status</h3>
        {isTerminalOffTrack ? (
          <p className="text-sm text-stone-600 dark:text-stone-400">
            This order was <span className="font-medium capitalize">{order.status}</span>.
          </p>
        ) : (
          <ol className="flex flex-wrap gap-2">
            {STATUS_TIMELINE.map((step, index) => (
              <li
                key={step}
                className={`rounded-full px-3 py-1 text-xs font-medium capitalize ${
                  index <= currentIndex
                    ? "bg-amber-700 text-white"
                    : "bg-stone-100 text-stone-500 dark:bg-stone-800 dark:text-stone-400"
                }`}
              >
                {step}
              </li>
            ))}
          </ol>
        )}

        {order.status_history.length > 0 ? (
          <ul className="mt-4 flex flex-col gap-1 border-l border-stone-200 pl-4 text-sm text-stone-600 dark:border-stone-800 dark:text-stone-400">
            {order.status_history.map((entry, index) => (
              <li key={index}>
                <span className="font-medium capitalize text-stone-900 dark:text-stone-100">{entry.to_status.replace(/_/g, " ")}</span>{" "}
                — {formatDate(entry.created_at)}
                {entry.note ? <span> ({entry.note})</span> : null}
              </li>
            ))}
          </ul>
        ) : null}
      </section>

      {/* Delivery tracking */}
      {order.delivery ? (
        <section>
          <h3 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">Delivery tracking</h3>
          <p className="text-sm text-stone-600 dark:text-stone-400">
            Status: <span className="font-medium capitalize text-stone-900 dark:text-stone-100">{order.delivery.status.replace(/_/g, " ")}</span>
            {order.delivery.courier_name ? ` · Courier: ${order.delivery.courier_name}` : ""}
            {order.delivery.tracking_number ? ` · Tracking #: ${order.delivery.tracking_number}` : ""}
          </p>
          {order.delivery.tracking_events.length > 0 ? (
            <ul className="mt-2 flex flex-col gap-1 border-l border-stone-200 pl-4 text-sm text-stone-600 dark:border-stone-800 dark:text-stone-400">
              {order.delivery.tracking_events.map((event, index) => (
                <li key={index}>
                  <span className="font-medium capitalize text-stone-900 dark:text-stone-100">{event.status.replace(/_/g, " ")}</span>
                  {event.location ? ` — ${event.location}` : ""} · {formatDate(event.occurred_at)}
                  {event.note ? ` (${event.note})` : ""}
                </li>
              ))}
            </ul>
          ) : null}
        </section>
      ) : order.estimated_delivery_minutes ? (
        <section>
          <h3 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">Delivery tracking</h3>
          <p className="text-sm text-stone-600 dark:text-stone-400">
            Estimated delivery within {Math.round(order.estimated_delivery_minutes / 60) || 1} hour(s) of confirmation — tracking details will
            appear here once your order is dispatched.
          </p>
        </section>
      ) : null}

      {/* Items */}
      <section>
        <h3 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">Items</h3>
        <ul className="flex flex-col gap-2">
          {order.items.map((item) => (
            <li key={item.id} className="flex justify-between gap-3 text-sm">
              <div>
                <p className="text-stone-900 dark:text-stone-100">
                  {item.product_name} {item.variant_name ? `(${item.variant_name})` : ""} × {item.quantity}
                </p>
                {item.customization_note ? (
                  <p className="text-xs italic text-stone-500 dark:text-stone-500">“{item.customization_note}”</p>
                ) : null}
              </div>
              <span className="shrink-0 text-stone-600 dark:text-stone-400">{formatPrice(item.line_total, order.currency)}</span>
            </li>
          ))}
        </ul>
      </section>

      {/* Delivery address */}
      {order.shipping_address_snapshot ? (
        <section>
          <h3 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">Delivery address</h3>
          <p className="text-sm text-stone-600 dark:text-stone-400">
            {order.shipping_address_snapshot.recipient_name} · {order.shipping_address_snapshot.phone}
            <br />
            {order.shipping_address_snapshot.line1}
            {order.shipping_address_snapshot.line2 ? `, ${order.shipping_address_snapshot.line2}` : ""}
            <br />
            {[order.shipping_address_snapshot.area, order.shipping_address_snapshot.city].filter(Boolean).join(", ")}
          </p>
        </section>
      ) : null}

      {/* Totals */}
      <section className="rounded-lg border border-stone-200 p-4 dark:border-stone-800">
        <div className="flex justify-between text-sm text-stone-600 dark:text-stone-400">
          <span>Subtotal</span>
          <span>{formatPrice(order.subtotal, order.currency)}</span>
        </div>
        {order.discount_total > 0 ? (
          <div className="flex justify-between text-sm text-stone-600 dark:text-stone-400">
            <span>Discount</span>
            <span>−{formatPrice(order.discount_total, order.currency)}</span>
          </div>
        ) : null}
        <div className="flex justify-between text-sm text-stone-600 dark:text-stone-400">
          <span>Delivery fee ({order.delivery_type})</span>
          <span>{formatPrice(order.delivery_fee, order.currency)}</span>
        </div>
        <div className="mt-2 flex justify-between border-t border-stone-200 pt-2 font-semibold text-stone-900 dark:border-stone-800 dark:text-stone-100">
          <span>Total</span>
          <span>{formatPrice(order.total, order.currency)}</span>
        </div>
      </section>
    </div>
  );
}
