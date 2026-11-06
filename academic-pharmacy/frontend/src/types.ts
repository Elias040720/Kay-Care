export type StockStatus = 'in_stock' | 'low_stock' | 'out_of_stock'

export type Illustration = 'capsule' | 'bottle' | 'drops' | 'tube' | 'box' | 'spray' | 'sachet' | 'jar'

export interface Category {
  id: number
  name: string
  slug: string
  description: string | null
  products_count?: number
}

export interface Product {
  id: number
  sku: string
  name: string
  slug: string
  brand: string
  short_description: string
  description: string
  presentation: string
  price_cents: number
  stock: number
  stock_status: StockStatus
  image_url: string | null
  illustration: Illustration
  is_featured: boolean
  category?: Category
}

export interface AdminProduct extends Product {
  category_id: number
  low_stock_threshold: number
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface PageMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from?: number | null
  to?: number | null
}

export interface Paginated<T> {
  data: T[]
  meta: PageMeta
}

export type OrderStatus = 'pending' | 'confirmed' | 'preparing' | 'ready' | 'delivered' | 'cancelled'

export interface OrderItem {
  product_id: number | null
  product_name: string
  sku: string
  unit_price_cents: number
  quantity: number
  line_total_cents: number
}

export interface OrderHistoryEntry {
  from_status: OrderStatus | null
  to_status: OrderStatus
  note: string | null
  admin: string | null
  created_at: string
}

export interface Order {
  id: number
  number: string
  buyer_alias: string
  status: OrderStatus
  status_label: string
  allowed_transitions: OrderStatus[]
  items_count: number
  subtotal_cents: number
  tax_cents: number
  total_cents: number
  created_at: string
  status_changed_at: string | null
  items?: OrderItem[]
  history?: OrderHistoryEntry[]
  simulated: true
}

export interface CartLineInput {
  product_id: number
  quantity: number
}

export interface CartIssue {
  product_id: number
  code: 'insufficient_stock' | 'unavailable'
  available: number
  requested: number
  name?: string
}

export interface CartValidation {
  valid: boolean
  lines: Array<{
    product_id: number
    sku: string
    name: string
    unit_price_cents: number
    quantity: number
    line_total_cents: number
    available_stock: number
  }>
  issues: CartIssue[]
  totals: { items_count: number; subtotal_cents: number; tax_cents: number; total_cents: number }
}

export interface CheckoutResponse {
  data: Order
  lookup_token: string
  replayed: boolean
  notice: string
}

export interface AdminUser {
  id: number
  name: string
  email: string
  last_login_at: string | null
}

export interface StockMovement {
  id: number
  product: { id: number; name: string; sku: string } | null
  delta: number
  stock_after: number
  reason: 'checkout' | 'adjustment' | 'restock' | 'cancellation' | 'seed'
  reference: string | null
  note: string | null
  admin: string | null
  created_at: string
}

export interface DashboardSummary {
  orders_total: number
  orders_by_status: Record<OrderStatus, number>
  revenue_cents: number
  average_ticket_cents: number
  products_total: number
  products_active: number
  units_in_stock: number
  low_stock: Array<{ id: number; name: string; sku: string; stock: number; low_stock_threshold: number; is_active: boolean }>
  top_products: Array<{ name: string; units: number; revenue_cents: number }>
  daily: Array<{ date: string; orders: number; revenue_cents: number }>
  generated_at: string
  disclaimer: string
}
