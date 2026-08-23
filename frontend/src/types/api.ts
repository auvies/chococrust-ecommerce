/**
 * Shape of every backend API resource this frontend consumes, mirrored from
 * the Laravel API Resources in `backend/app/Http/Resources/*` (Phase 04).
 * Keep these in sync with the backend resource `toArray()` field lists —
 * this file is the single source of truth for what the storefront expects.
 */

export interface PaginationLinks {
  first: string | null;
  last: string | null;
  prev: string | null;
  next: string | null;
}

export interface PaginationMeta {
  current_page: number;
  from: number | null;
  last_page: number;
  path: string;
  per_page: number;
  to: number | null;
  total: number;
}

export interface Paginated<T> {
  data: T[];
  links: PaginationLinks;
  meta: PaginationMeta;
}

export interface ApiEnvelope<T> {
  data: T;
}

export interface ProductVariant {
  id: number;
  sku: string;
  name: string;
  price: number;
  compare_at_price: number | null;
  currency: string;
  weight_grams: number | null;
  attributes: Record<string, unknown> | null;
  is_default: boolean;
  is_active: boolean;
}

export interface ProductMedia {
  id: number;
  type: string;
  url: string;
  alt_text: string | null;
  sort_order: number;
  is_primary: boolean;
}

export interface Category {
  id: number;
  parent_id: number | null;
  name: string;
  slug: string;
  description: string | null;
  image_url: string | null;
  alt_text: string | null;
  is_active: boolean;
  sort_order: number;
  children: Category[];
  created_at: string;
  updated_at: string;
}

export interface Product {
  id: number;
  category_id: number | null;
  name: string;
  slug: string;
  description: string | null;
  short_description: string | null;
  brand: string | null;
  status: string;
  is_featured: boolean;
  /** Only present on the product detail response (GET /products/{id}) - aggregated across every approved review. */
  rating_average?: number | null;
  rating_count?: number;
  variants: ProductVariant[];
  media: ProductMedia[];
  categories: Category[];
  created_at: string;
  updated_at: string;
}

export interface Review {
  id: number;
  product_id: number;
  rating: number;
  title: string | null;
  body: string | null;
  is_approved: boolean;
  is_verified_purchase: boolean;
  created_at: string;
}

export interface HeroBanner {
  id: number;
  title: string;
  subtitle: string | null;
  /** The desktop image - see `mobile_image_url` for the mobile crop. */
  image_url: string | null;
  mobile_image_url: string | null;
  alt_text: string | null;
  link_url: string | null;
  /** The CTA button's label; `link_url` is where it goes. */
  cta_text: string | null;
  sort_order: number;
  is_active: boolean;
  starts_at: string | null;
  ends_at: string | null;
}

export interface ThemeConfig {
  background: string;
  surface: string;
  text_color: string;
  primary_color: string;
  secondary_color: string;
  typography: {
    font_family: string;
    heading_font_family: string;
  };
  font_sizes: {
    sm: string;
    base: string;
    lg: string;
    xl: string;
    "2xl": string;
  };
  buttons: {
    radius: string;
    primary_bg: string;
    primary_text: string;
    secondary_bg: string;
    secondary_text: string;
  };
}

export interface HomepageSection {
  id: number;
  type: string;
  title: string | null;
  config: Record<string, unknown> | null;
  sort_order: number;
  is_active: boolean;
}

export interface SeoMetadata {
  id: number;
  meta_title: string | null;
  meta_description: string | null;
  meta_keywords: string | null;
  canonical_url: string | null;
  og_title: string | null;
  og_description: string | null;
  og_image_url: string | null;
}

export interface SystemSetting {
  key: string;
  value: string | null;
  type: string;
  group: string | null;
  /** Only present for callers with settings.manage. */
  is_public?: boolean;
}

export interface Theme {
  id: number;
  name: string;
  slug: string;
  config: ThemeConfig;
  is_active: boolean;
}

export interface SocialAccountStatus {
  platform: "facebook" | "instagram";
  connected: boolean;
  page_id?: string | null;
  business_account_id?: string | null;
}

export interface AuditLog {
  id: number;
  user_id: number | null;
  action: string;
  auditable_type: string | null;
  auditable_id: number | null;
  before: Record<string, unknown> | null;
  after: Record<string, unknown> | null;
  ip_address: string | null;
  created_at: string;
}

export interface DeliveryRule {
  id: number;
  scope: "global" | "category" | "product";
  category_id: number | null;
  product_id: number | null;
  delivery_type: "local" | "nationwide" | "both";
  is_deliverable: boolean;
  min_order_amount: number | null;
  flat_fee: number | null;
  local_areas: string[] | null;
  estimated_minutes: number | null;
  priority: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface Inventory {
  id: number;
  product_variant_id: number;
  location: string;
  quantity_on_hand: number;
  quantity_reserved: number | null;
  quantity_available: number | null;
  quantity_sold: number | null;
  reorder_level: number | null;
  updated_at: string;
}

export interface InventoryMovement {
  id: number;
  product_variant_id: number;
  type: string;
  quantity_delta: number;
  inventory_reservation_id: number | null;
  order_id: number | null;
  reason: string | null;
  created_by: number | null;
  created_at: string;
}

export interface Address {
  id: number;
  label: string | null;
  type: string;
  recipient_name: string;
  phone: string;
  line1: string;
  line2: string | null;
  city: string;
  area: string | null;
  province: string | null;
  postal_code: string | null;
  country: string;
  is_default: boolean;
}

export interface CustomerTag {
  id: number;
  name: string;
  color: string | null;
  created_at: string;
}

export interface CustomerNote {
  id: number;
  customer_id: number;
  body: string;
  author_id: number | null;
  author_name: string | null;
  created_at: string;
}

export interface Customer {
  id: number;
  name: string | null;
  email: string | null;
  phone: string | null;
  date_of_birth: string | null;
  marketing_opt_in: boolean;
  notes?: string;
  tags?: CustomerTag[];
  addresses: Address[];
  created_at: string;
}

export interface OrderItem {
  id: number;
  product_id: number | null;
  product_variant_id: number | null;
  product_name: string;
  variant_name: string | null;
  sku: string;
  unit_price: number;
  quantity: number;
  customization_note: string | null;
  line_total: number;
}

export interface OrderStatusHistory {
  from_status: string | null;
  to_status: string;
  note: string | null;
  changed_by: number | null;
  created_at: string;
}

export type OrderStatus =
  | "pending"
  | "confirmed"
  | "processing"
  | "ready"
  | "dispatched"
  | "delivered"
  | "completed"
  | "cancelled"
  | "refunded";

export interface Order {
  id: number;
  order_number: string;
  status: OrderStatus;
  currency: string;
  subtotal: number;
  discount_total: number;
  delivery_fee: number;
  tax_total: number;
  total: number;
  delivery_type: string;
  estimated_delivery_minutes: number | null;
  shipping_address_snapshot: Partial<Address> | null;
  contact_name: string;
  contact_phone: string;
  contact_email: string | null;
  notes: string | null;
  placed_at: string | null;
  items: OrderItem[];
  status_history: OrderStatusHistory[];
  delivery: Delivery | null;
  created_at: string;
}

export interface Payment {
  id: number;
  order_id: number;
  method: string;
  status: string;
  amount: number;
  currency: string;
  gateway: string | null;
  gateway_reference?: string | null;
  paid_at: string | null;
  refunded_total?: number;
}

export interface CodRecord {
  id: number;
  order_id: number;
  status: string;
  amount_due: number;
  amount_collected: number | null;
  collected_by: number | null;
  collected_at: string | null;
  verified_by: number | null;
  verified_at: string | null;
}

export interface DeliveryTrackingEvent {
  status: string;
  location: string | null;
  note: string | null;
  occurred_at: string;
}

export interface Delivery {
  id: number;
  order_id: number;
  type: string;
  courier_name: string | null;
  rider_id: number | null;
  tracking_number: string | null;
  status: string;
  delivery_attempts: number;
  failure_reason: string | null;
  delivery_fee: number;
  estimated_delivery_at: string | null;
  delivered_at: string | null;
  tracking_events: DeliveryTrackingEvent[];
}

export interface AdminUser {
  id: number;
  name: string;
  email: string;
  type: string;
  is_active: boolean;
  roles: string[];
  last_login_at: string | null;
  created_at: string;
}

export interface Role {
  slug: string;
  name: string;
  description: string | null;
  permissions: string[];
}

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  type: string;
  roles: string[];
  permissions: string[];
  two_factor_enabled: boolean;
}

export interface ChatMessage {
  id: number;
  sender_type: "customer" | "ai" | "staff";
  content: string;
  created_at: string;
}

export interface ChatConversation {
  id: number;
  channel: string;
  status: "open" | "closed" | "escalated";
  started_at: string | null;
  ended_at: string | null;
  messages: ChatMessage[];
}

export interface AgentPendingAction {
  id: number;
  tool_name: string;
  agent_type: string | null;
  agent_id: number;
  input: Record<string, unknown>;
  status: "pending" | "executed" | "rejected" | "failed";
  reviewed_by: number | null;
  reviewed_at: string | null;
  rejection_reason: string | null;
  result: Record<string, unknown> | null;
  error: string | null;
  created_at: string;
}

export interface NotificationTemplate {
  id: number;
  key: string;
  channel: "database" | "mail" | "whatsapp";
  subject: string | null;
  body: string;
  is_active: boolean;
  updated_at: string;
}

/** Laravel's polymorphic notifications table, as returned by GET /notifications - a customer's own order/payment/delivery updates. */
export interface AppNotification {
  id: string;
  type: string;
  data: { body?: string; order_id?: number; order_number?: string; [key: string]: unknown };
  read_at: string | null;
  created_at: string;
}
