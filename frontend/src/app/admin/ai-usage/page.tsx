"use client";

import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { useAdminResource } from "@/lib/hooks/useAdminList";
import { getAiUsage, type AiUsageOverview } from "@/lib/api/admin/analytics";
import { PERMISSIONS } from "@/lib/permissions";

export default function AiUsagePage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.analyticsView]}>
      <AiUsageDashboard />
    </RequirePermission>
  );
}

const EMPTY: AiUsageOverview = {
  total_replies: 0,
  total_cost_usd: 0,
  total_input_tokens: 0,
  total_output_tokens: 0,
  by_provider: [],
  by_status: {},
  by_model: [],
  by_purpose: [],
  by_agent_type: [],
  daily: [],
  limits: {
    max_ai_calls_per_conversation_per_hour: 0,
    max_agent_tool_calls_per_hour: 0,
    daily_cost_limit_usd: 0,
    daily_cost_spent_usd: 0,
    daily_request_limit: 0,
    daily_requests_used: 0,
  },
};

function AiUsageDashboard() {
  const { data, loading } = useAdminResource(getAiUsage, EMPTY);

  const deterministic = data.by_provider.find((p) => p.provider === "internal")?.count ?? 0;
  const aiCalls = data.by_provider.filter((p) => p.provider !== "internal").reduce((sum, p) => sum + p.count, 0);

  return (
    <div>
      <PageHeader title="AI Chatbot Usage & Cost" />
      <p className="-mt-2 mb-6 text-sm text-stone-500 dark:text-stone-400">
        Last 30 days. Deterministic replies (prices, stock, delivery, hours, payment methods, order status, contact) never
        call the AI provider and cost nothing — only replies that genuinely needed AI do.
      </p>

      {loading ? (
        <p className="text-sm text-stone-500">Loading…</p>
      ) : (
        <>
          <div className="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <StatCard label="Total replies" value={String(data.total_replies)} />
            <StatCard label="Answered without AI" value={String(deterministic)} />
            <StatCard label="Answered with AI" value={String(aiCalls)} />
            <StatCard label="Estimated AI cost" value={`$${data.total_cost_usd.toFixed(4)}`} />
            <StatCard label="Input tokens" value={String(data.total_input_tokens)} />
            <StatCard label="Output tokens" value={String(data.total_output_tokens)} />
            <StatCard
              label="Today's spend vs. limit"
              value={data.limits.daily_cost_limit_usd > 0 ? `$${data.limits.daily_cost_spent_usd.toFixed(4)} / $${data.limits.daily_cost_limit_usd.toFixed(2)}` : `$${data.limits.daily_cost_spent_usd.toFixed(4)} (no limit)`}
            />
            <StatCard
              label="Today's requests vs. limit"
              value={data.limits.daily_request_limit > 0 ? `${data.limits.daily_requests_used} / ${data.limits.daily_request_limit}` : `${data.limits.daily_requests_used} (no limit)`}
            />
          </div>

          <section className="mb-8">
            <h2 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">By provider</h2>
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-stone-200 text-xs uppercase tracking-wide text-stone-500 dark:border-stone-800">
                  <th className="py-2">Provider</th>
                  <th className="py-2">Replies</th>
                  <th className="py-2">Input tokens</th>
                  <th className="py-2">Output tokens</th>
                  <th className="py-2">Cost (USD)</th>
                </tr>
              </thead>
              <tbody>
                {data.by_provider.map((row) => (
                  <tr key={row.provider} className="border-b border-stone-100 dark:border-stone-900">
                    <td className="py-2 capitalize">{row.provider}</td>
                    <td className="py-2">{row.count}</td>
                    <td className="py-2">{row.input_tokens}</td>
                    <td className="py-2">{row.output_tokens}</td>
                    <td className="py-2">${Number(row.cost_usd).toFixed(4)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </section>

          <section className="mb-8">
            <h2 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">By status</h2>
            <div className="flex gap-4 text-sm">
              {Object.entries(data.by_status).map(([status, count]) => (
                <span key={status} className="rounded-md bg-stone-100 px-3 py-1.5 capitalize dark:bg-stone-900">
                  {status}: {count}
                </span>
              ))}
              {Object.keys(data.by_status).length === 0 ? <span className="text-stone-500">No activity yet.</span> : null}
            </div>
          </section>

          <section className="mb-8">
            <h2 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">By model</h2>
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-stone-200 text-xs uppercase tracking-wide text-stone-500 dark:border-stone-800">
                  <th className="py-2">Model</th>
                  <th className="py-2">Calls</th>
                  <th className="py-2">Input tokens</th>
                  <th className="py-2">Output tokens</th>
                  <th className="py-2">Cost (USD)</th>
                </tr>
              </thead>
              <tbody>
                {data.by_model.map((row) => (
                  <tr key={row.model} className="border-b border-stone-100 dark:border-stone-900">
                    <td className="py-2">{row.model}</td>
                    <td className="py-2">{row.count}</td>
                    <td className="py-2">{row.input_tokens}</td>
                    <td className="py-2">{row.output_tokens}</td>
                    <td className="py-2">${Number(row.cost_usd).toFixed(4)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </section>

          <section>
            <h2 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">Agent tool-call usage</h2>
            <p className="mb-2 text-xs text-stone-500 dark:text-stone-400">Which provisioned agent identity is generating tool-call volume (Phase 13 agent architecture) — none of the 8 named agents are provisioned in this environment yet.</p>
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-stone-200 text-xs uppercase tracking-wide text-stone-500 dark:border-stone-800">
                  <th className="py-2">Agent type</th>
                  <th className="py-2">Calls</th>
                  <th className="py-2">Cost (USD)</th>
                </tr>
              </thead>
              <tbody>
                {data.by_agent_type.map((row) => (
                  <tr key={row.agent_type ?? "unassigned"} className="border-b border-stone-100 dark:border-stone-900">
                    <td className="py-2">{row.agent_type ?? "(unassigned)"}</td>
                    <td className="py-2">{row.count}</td>
                    <td className="py-2">${Number(row.cost_usd).toFixed(4)}</td>
                  </tr>
                ))}
                {data.by_agent_type.length === 0 ? (
                  <tr>
                    <td className="py-2 text-stone-500" colSpan={3}>No agent tool calls yet.</td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </section>
        </>
      )}
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
