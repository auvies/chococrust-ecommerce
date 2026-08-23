const TONE: Record<string, string> = {
  active: "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400",
  paid: "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400",
  delivered: "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400",
  completed: "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400",
  deposited: "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400",
  approved: "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400",
  executed: "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400",
  pending: "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400",
  cod_pending: "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400",
  cod_confirmed: "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400",
  awaiting_delivery: "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400",
  processing: "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400",
  ready: "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400",
  out_for_delivery: "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400",
  // Cash physically collected but not yet staff-verified/deposited -
  // deliberately distinct from `paid` (see PaymentService::collectCod()
  // vs verifyCod()): a rider's own claim isn't the same certainty as a
  // reconciled deposit.
  cod_collected: "bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-400",
  collected: "bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-400",
  draft: "bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-300",
  inactive: "bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-300",
  cancelled: "bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-400",
  archived: "bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-400",
  refunded: "bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-400",
  partially_refunded: "bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-400",
  failed: "bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-400",
  failed_collection: "bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-400",
  returned: "bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-400",
  rejected: "bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-400",
};

export function StatusBadge({ status }: { status: string }) {
  const tone = TONE[status] ?? "bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-300";
  return <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${tone}`}>{status.replace(/_/g, " ")}</span>;
}
