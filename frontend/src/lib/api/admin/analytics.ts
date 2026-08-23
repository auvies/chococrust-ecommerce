import { adminFetch } from "@/lib/api/admin/client";
import type { ApiEnvelope } from "@/types/api";

export interface AnalyticsOverview {
  orders_by_status: Record<string, number>;
  orders_total: number;
  revenue_paid: number;
}

export interface SalesPoint {
  date: string;
  orders: number;
  total: number;
}

export async function getAnalyticsOverview(): Promise<AnalyticsOverview> {
  const { data } = await adminFetch<ApiEnvelope<AnalyticsOverview>>("/v1/analytics/overview");
  return data;
}

export async function getAnalyticsSales(): Promise<SalesPoint[]> {
  const { data } = await adminFetch<ApiEnvelope<SalesPoint[]>>("/v1/analytics/sales");
  return data;
}

export interface OrderAnalytics {
  orders_total: number;
  by_status: Record<string, number>;
  average_order_value: number;
  cancelled_orders: number;
  cancellation_rate: number;
}

export async function getOrderAnalytics(): Promise<OrderAnalytics> {
  const { data } = await adminFetch<ApiEnvelope<OrderAnalytics>>("/v1/analytics/orders");
  return data;
}

export interface ProductPerformanceRow {
  product_id: number;
  name: string;
  slug: string | null;
  quantity_sold: number;
  revenue: number;
  order_count: number;
}

export async function getProductPerformance(): Promise<ProductPerformanceRow[]> {
  const { data } = await adminFetch<ApiEnvelope<ProductPerformanceRow[]>>("/v1/analytics/products");
  return data;
}

export async function getBestSellers(): Promise<ProductPerformanceRow[]> {
  const { data } = await adminFetch<ApiEnvelope<ProductPerformanceRow[]>>("/v1/analytics/best-sellers");
  return data;
}

export interface TopCustomer {
  customer_id: number;
  name: string | null;
  email: string | null;
  orders_count: number;
  total_spent: number;
}

export interface CustomerAnalytics {
  new_customers: number;
  customers_with_orders: number;
  repeat_customers: number;
  repeat_customer_rate: number;
  top_customers: TopCustomer[];
}

export async function getCustomerAnalytics(): Promise<CustomerAnalytics> {
  const { data } = await adminFetch<ApiEnvelope<CustomerAnalytics>>("/v1/analytics/customers");
  return data;
}

export interface CodAnalytics {
  total_cod_records: number;
  by_status: Record<string, number>;
  collected: number;
  failed: number;
  awaiting_delivery: number;
  success_rate: number | null;
}

export async function getCodAnalytics(): Promise<CodAnalytics> {
  const { data } = await adminFetch<ApiEnvelope<CodAnalytics>>("/v1/analytics/cod");
  return data;
}

export interface DeliveryAnalytics {
  total_deliveries: number;
  by_status: Record<string, number>;
  delivered: number;
  failed: number;
  returned: number;
  failed_delivery_rate: number | null;
  average_delivery_attempts: number;
}

export async function getDeliveryAnalytics(): Promise<DeliveryAnalytics> {
  const { data } = await adminFetch<ApiEnvelope<DeliveryAnalytics>>("/v1/analytics/deliveries");
  return data;
}

export interface LowStockRow {
  product_variant_id: number;
  sku: string | null;
  name: string | null;
  location: string;
  available: number;
  reorder_level: number;
}

export interface InventoryAnalytics {
  inventory_records_tracked: number;
  total_units_on_hand: number;
  total_units_available: number;
  out_of_stock_count: number;
  low_stock_count: number;
  low_stock: LowStockRow[];
}

export async function getInventoryAnalytics(): Promise<InventoryAnalytics> {
  const { data } = await adminFetch<ApiEnvelope<InventoryAnalytics>>("/v1/analytics/inventory");
  return data;
}

export interface PaymentsByMethod {
  method: string;
  count: number;
  total_amount: number;
}

export interface PaymentAnalytics {
  total_payments: number;
  total_paid_amount: number;
  by_method: PaymentsByMethod[];
  by_status: Record<string, number>;
  failure_rate: number;
  refund_rate: number;
}

export async function getPaymentAnalytics(): Promise<PaymentAnalytics> {
  const { data } = await adminFetch<ApiEnvelope<PaymentAnalytics>>("/v1/analytics/payments");
  return data;
}

export interface AiUsageByProvider {
  provider: string;
  count: number;
  cost_usd: number;
  input_tokens: number;
  output_tokens: number;
}

export interface AiUsageByModel {
  model: string;
  count: number;
  cost_usd: number;
  input_tokens: number;
  output_tokens: number;
}

export interface AiUsageByPurpose {
  purpose: string;
  count: number;
  cost_usd: number;
}

export interface AiUsageByAgentType {
  agent_type: string | null;
  count: number;
  cost_usd: number;
}

export interface AiUsageDailyPoint {
  date: string;
  calls: number;
  cost_usd: number;
}

export interface AiUsageLimits {
  max_ai_calls_per_conversation_per_hour: number;
  max_agent_tool_calls_per_hour: number;
  daily_cost_limit_usd: number;
  daily_cost_spent_usd: number;
  daily_request_limit: number;
  daily_requests_used: number;
}

export interface AiUsageOverview {
  total_replies: number;
  total_cost_usd: number;
  total_input_tokens: number;
  total_output_tokens: number;
  by_provider: AiUsageByProvider[];
  by_status: Record<string, number>;
  by_model: AiUsageByModel[];
  by_purpose: AiUsageByPurpose[];
  by_agent_type: AiUsageByAgentType[];
  daily: AiUsageDailyPoint[];
  limits: AiUsageLimits;
}

/** Cost monitoring (CLAUDE.md §19) - how many chatbot replies/agent tool calls were free (deterministic/internal) vs. AI-generated, what they cost, and the configured usage limits against today's actual usage. */
export async function getAiUsage(): Promise<AiUsageOverview> {
  const { data } = await adminFetch<ApiEnvelope<AiUsageOverview>>("/v1/analytics/ai-usage");
  return data;
}
